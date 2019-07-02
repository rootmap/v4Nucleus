<?php

namespace App\Http\Controllers;
use App\CounterDisplay;
use App\Invoice;
use App\Category;
use App\Product;
use App\Tender;
use App\Customer;
use App\Payout;
use App\AuthorizeNetPayment;
use App\User;
use App\InvoiceProduct;
use App\InvoicePayment;
use App\TicketProblem;
use App\SessionInvoice;
use App\RetailPosSummary;
use App\RetailPosSummaryDateWise;
use App\PosSetting;
use App\SalesReturn;
use App\OpenDrawer;
use App\CloseDrawer;
use App\SendSalesEmail;
use App\AuthorizeNetPaymentHistory;
use App\CardInfo;
use App\InStoreRepair;
use App\InStoreTicket;
use Carbon\Carbon;
use Mpdf\Mpdf;
use App\Pos;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
//paypal lib
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
//paypal lib 

//instore repair
use App\InStoreRepairDevice;
use App\InStoreRepairModel;
use App\InStoreRepairProblem;
use App\InStoreRepairPrice;

use Stripe;
use App\StripeStoreSetting;
use App\StripeTransactionHistory;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    private $moduleName="Sales";
    private $sdc;
    private $_api_content;
    public function __construct(){ 
        
        $paypal_conf=\Config::get('paypal');
        $this->_api_content=new ApiContext(new OAuthTokenCredential(
            $paypal_conf['client_id'],
            $paypal_conf['secret']
        ));

        $this->_api_content->setConfig($paypal_conf['settings']);

        $this->sdc = new StaticDataController(); 
        $this->authorizenet = new AuthorizeNetPaymentController(); 
    }

    public function salesPartialAdd(Request $request)
    {
        /*$tab=\DB::table('menu_pages')->orderBy('id','DESC')->get();
        return view('apps.pages.sales.addPayment',
                        [
                            'dataTable'=>$tab
                        ]
                    );*/

        Session::put('addPartialPayment',1);
        return redirect(url('pos'));
    }

    public function posclear(Request $request)
    {
        $oldCart = $request->session()->has('Pos') ? $request->session()->get('Pos') : null;
        $cart = new Pos($oldCart);
        $cart->ClearCart();
        Session::put('Pos', $cart);

        return redirect('pos');
    } 


    //paypal intregation start
    public function paywithpaypal()
    {
        // After Step 2
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        $item1 = new Item();
        $item1->setName('Ground Coffee 40 oz')
            ->setCurrency('USD')
            ->setQuantity(1)
            ->setSku("123123") // Similar to `item_number` in Classic API
            ->setPrice(7.5);
        $item2 = new Item();
        $item2->setName('Granola bars')
            ->setCurrency('USD')
            ->setQuantity(5)
            ->setSku("321321") // Similar to `item_number` in Classic API
            ->setPrice(2);

        $itemList = new ItemList();
        $itemList->setItems(array($item1, $item2));   

        $details = new Details();
        $details->setShipping(1.2)
            ->setTax(1.3)
            ->setSubtotal(17.50); 

        $amount = new Amount();
        $amount->setCurrency("USD")
            ->setTotal(20)
            ->setDetails($details);   
        
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Payment description")
            ->setInvoiceNumber(uniqid()); 

        //$baseUrl = url();
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(url('paypal/success'))
            ->setCancelUrl(url('paypal/cancel'));

        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));


        try {
            $payment->create($this->_api_content);
        } catch (\PayPal\Exception\PPConnectionException $ex) {
            if(\Config::get('app.debug'))
            {
                \Session::put('error','Connection has timeout.!!!!, Please try again.');
                return redirect('paypal');
            }
            else
            {
                \Session::put('error','Something went wrong.!!!!, Please try again.');
                return redirect('paypal');
            }
        }


        foreach($payment->getLinks() as $link){
            if($link->getRel()=='approval_url')
            {
                $redirect_url=$link->getHref();
                break;
            }
        }

        \Session::put('paypal_payment_id',$payment->getId());

        if(isset($redirect_url))
        {
            return redirect($redirect_url);
        }

        \Session::put('error','Unknown error occured, Please try again.!!!!!');
        return redirect('paypal');

    }



    public function developer(Request $request)
    {
        $product=Product::where('category_name','Repair')->limit('500')->get();
        return view('apps.pages.developer.product_name_replace',compact('product'));
    }

    public function getUnsavedInvoice(Request $request)
    {
        $sessionInvoice=SessionInvoice::where('store_id',$this->sdc->storeID())
                                      ->where('invoice_status','Not Created')
                                      ->get();

        $dataArray=[];
        if(count($sessionInvoice)>0)
        {
            foreach($sessionInvoice as $row)
            {
                $posSession=json_decode(unserialize($row->session_pos_data));

                $customerName="";
                if(count($posSession->customerID)>0)
                {
                    $cusInfo=Customer::find($posSession->customerID);
                    if(isset($cusInfo))
                    {
                        $customerName=$cusInfo->name;
                    }
                    
                }
                

                //dd($posSession->customerID);
                $adata=array(
                    'id'=>$row->id,
                    'invoice_id'=>$row->invoice_id,
                    'invoice_status'=>$row->invoice_status,
                    'totalPrice'=>$posSession->totalPrice,
                    'customerName'=>$customerName,
                    'created_at'=>formatDateTime($row->created_at)
                );

                $dataArray[]=$adata;
            }
        }

        $sessionInvoice=$dataArray;

        return view('apps.pages.unsaved.list',compact('sessionInvoice'));
    }

    public function genarateUnsavedInvoice(Request $request,$id=0)
    {
        $sessionInvoice=SessionInvoice::where('store_id',$this->sdc->storeID())
                                      ->where('id',$id)
                                      ->first();

        $dawSerializePos=unserialize($sessionInvoice->session_pos_data);
        $decodePos=json_decode($dawSerializePos,true);
        $decodePosOnly=json_decode($dawSerializePos);

        $oldCart = $request->session()->has('Pos') ? $request->session()->get('Pos') : null;
        $oldCartItems=0;
        if(isset($oldCart->items))
        {
            $oldCartItems=count($oldCart->items);
        }
    
        $denew=$decodePos['items'];

        unset($decodePosOnly->items);

        //dd($denew);
        
            //dd(
        
        $decodePosOnly->items=$denew;

        //dd($decodePosOnly);
        
        if($oldCartItems>0)
        {
            return redirect(url('pos'))->with('status','Please clear pos Item to saved unsaved invoices.');
        }
        else
        {
            $request->session()->put('Pos', $decodePosOnly);
            $this->CompleteSalesPOS($request);

            return redirect(url('sales/unsaved/invoice'))->with('success','Invoice saved successfully.');
        }

        //dd($decodePos);
    }

    public function developerStore(Request $request)
    {
        //dd($_POST); die();
        if(isset($_POST['product_id']))
        {
            if(count($_POST['product_id'])>0)
            {
                $product_id=$_POST['product_id'];
                foreach ($product_id as $key => $pid) {
                    $newProductName=$_POST['product_new_name'][$key];
                    echo $pid; 
                    echo "_______________"; 
                    echo $newProductName."<br>";

                    \DB::table('products')->where('id',$pid)->update(['name'=>$newProductName]);
                }

                //die();
            }
        }
        
        //$product=Product::where('category_name','Repair')->limit('500')->get();
        //return view('apps.pages.developer.product_name_replace',compact('product'));
        return redirect(url('developer/console'))->with('success','Particular Data Updated Successfully.');
    }

    public function posRepairProduct(Request $request)
    {

        //echo $_POST['model_id']; die();
        $model_id=$request->model_id;
        $device_id=$request->device_id;
        $problem_id=$request->problem_id;
        $pro=\DB::table('in_store_repair_prices')
                  ->where('model_id',$model_id)
                  ->where('device_id',$device_id)
                  ->where('problem_id',$problem_id)
                  ->first();

        

        //$check_product=
        dd($request);
    }

    public function paypal(Request $request)
    {
       return view('apps.pages.paypal.paywithpaypal');
    }

    public function getPaymentStatus(Request $request,$status='fahad')
    {
        $payment_id=\Session::get('paypal_payment_id');
                    \Session::forget('paypal_payment_id');

        if(empty($request->PayerID) || empty($request->token))
        {
            \Session::put('error','Failed token mismatch, Please tryagain');
            return redirect('paypal');
        }

        $payment=Payment::get($payment_id,$this->_api_content);
        $excution=new PaymentExecution();
        $excution->setPayerId($request->PayerID);

        $result=$payment->execute($excution,$this->_api_content);

        if($result->getState()=='approved')
        {
            \Session::put('success','Payment successful.');
            return redirect('paypal'); die();
        }
        else
        {
            \Session::put('error','Payment Failed, Please tryagain');
            return redirect('paypal');
        }

    }

    public function getPaymentStatusPaypal(Request $request,$invoice_id=0,$status='fahad')
    {
        //dd($invoice_id);
        $payment_id=\Session::get('paypal_payment_id');
                    \Session::forget('paypal_payment_id');

        if(empty($request->PayerID) || empty($request->token))
        {
            \Session::put('error','Failed token mismatch, Please tryagain');
            return redirect('invoice/pay/'.$invoice_id);
        }

        $payment=Payment::get($payment_id,$this->_api_content);
        $excution=new PaymentExecution();
        $excution->setPayerId($request->PayerID);

        $result=$payment->execute($excution,$this->_api_content);
        //dd($invoice_id);
        if($result->getState()=='approved')
        {
            $trans=$result->getTransactions();
            //$amtAr=$trans->getAmount();
            $amountPaid=$trans[0]->getAmount()->getTotal();
            //dd($amountPaid);

            $tenderData=Tender::where('paypal',1)->first();

            $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
            $invoiceData->tender_id=$tenderData->id;
            $invoiceData->tender_name=$tenderData->name;
            $invoiceData->invoice_status='Paid';
            $total_profit=$invoiceData->total_amount-$invoiceData->total_cost;
            $invoiceData->total_profit=$total_profit;
            $invoiceData->save();

            $cusInfo=Customer::find($invoiceData->customer_id);

            $invoicePay=new InvoicePayment;
            $invoicePay->invoice_id=$invoice_id;
            $invoicePay->customer_id=$invoiceData->customer_id;
            $invoicePay->customer_name=$cusInfo->name;
            $invoicePay->tender_id=$tenderData->id;
            $invoicePay->tender_name=$tenderData->name;
            $invoicePay->total_amount=$invoiceData->total_amount;
            $invoicePay->paid_amount=$amountPaid;
            $invoicePay->store_id=$invoiceData->store_id;
            $invoicePay->created_by=$invoiceData->created_by;
            $invoicePay->save();
            
            \Session::put('success','Payment successful.');
            return redirect('invoice/pay/'.$invoice_id); die();
        }
        else
        {
            \Session::put('error','Payment Failed, Please tryagain');
           return redirect('invoice/pay/'.$invoice_id);
        }

    }

    public function savePayout(Request $request)
    {

        $userFullName=\Auth::user()->name;

        if(!empty($request->payout_amount))
        {
            $tab=new Payout();
            $dataFirstAmount=substr($request->payout_amount,0,1);

            if($dataFirstAmount=="-")
            {
                $tab->negative_amount=substr($request->payout_amount,1,100);
            }
            else
            {
                $tab->amount=$request->payout_amount;
            }

            $tab->cashier_id=$this->sdc->UserID();
            $tab->cashier_name=$userFullName;
            $tab->reason=$request->payout_reason;
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();

            return 1;
        }
        else
        {
            return 0;
        }
        

    }

    public function Payoutreport(Request $request)
    {
        //dd(1);

        $cashier_id='';
        if(isset($request->cashier_id))
        {
            $cashier_id=$request->cashier_id;
        }


        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }




        $invoice=Payout::select('id','cashier_id','cashier_name','amount','negative_amount','reason','created_at')
                     ->where('store_id',$this->sdc->storeID())
                     ->when($cashier_id, function ($query) use ($cashier_id) {
                            return $query->where('cashier_id','=', $cashier_id);
                     })
                     ->when($dateString, function ($query) use ($dateString) {
                            return $query->whereRaw($dateString);
                     })
                     ->get();
                     //->toSql();

        //dd($tender_id);              

        $tab_customer=\DB::table('users')->where('store_id',$this->sdc->storeID())->get();

        return view('apps.pages.report.payout',
            [
                'customer'=>$tab_customer,
                'invoice'=>$invoice,
                'cashier_id'=>$cashier_id,
                'start_date'=>$start_date,
                'end_date'=>$end_date
            ]);
    }

    public function PayoutQuery($request)
    {
        $cashier_id='';
        if(isset($request->cashier_id))
        {
            $cashier_id=$request->cashier_id;
        }        

       
        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }

        $invoice=Payout::where('store_id',$this->sdc->storeID())
                         ->when($cashier_id, function ($query) use ($cashier_id) {
                                return $query->where('cashier_id','=', $cashier_id);
                         })
                         ->when($dateString, function ($query) use ($dateString) {
                                return $query->whereRaw($dateString);
                         })
                         ->get();

        return $invoice;
    }

    public function exportPayoutExcel(Request $request) 
    {
        //excel 
        $data=array();
        $array_column=array('ID','Cashier/Manager Name','Amount','Negative Amount','Reason','Created');
        array_push($data, $array_column);
        $inv=$this->PayoutQuery($request);

        foreach($inv as $voi):
            $inv_arry=array($voi->id,$voi->cashier_name,$voi->amount,$voi->negative_amount,$voi->reason,formatDateTime($voi->created_at));
            array_push($data, $inv_arry);
        endforeach;

        $reportName="Payout Report";
        $report_title="Payout Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));
        /*$data = array(
            array('data1', 'data2'),
            array('data3', 'data4')
        );*/

        //array_unshift($data,$array_column);

       // dd($data);

        $excelArray=array(
            'report_name'=>$reportName,
            'report_title'=>$report_title,
            'report_description'=>$report_description,
            'data'=>$data
        );
        // dd($excelArray);
        $this->sdc->ExcelLayout($excelArray);
        
    }

    public function exportPayoutPDF(Request $request)
    {

        $data=array();
        
       
        $reportName="Payout Report";
        $report_title="Payout Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));

        $html='<table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >ID</th>
                <th class="text-center" style="font-size:12px;" >Cashier/Manager Name</th>
                <th class="text-center" style="font-size:12px;" >Amount</th>
                <th class="text-center" style="font-size:12px;" >Negative Amount</th>
                <th class="text-center" style="font-size:12px;" >Reason</th>
                <th class="text-center" style="font-size:12px;" >Created</th>
                </tr>
                </thead>
                <tbody>';

                    $inv=$this->PayoutQuery($request);
                    foreach($inv as $voi):
                        $html .='<tr>
                        <td style="font-size:12px;" class="text-center">'.$voi->id.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->cashier_name.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->amount.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->negative_amount.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->reason.'</td>
                        <td style="font-size:12px;" class="text-right">'.formatDateTime($voi->created_at).'</td>
                        </tr>';

                    endforeach;


                        

             
                /*html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : 4</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">00</td>
                </tr>';*/

                $html .='</tbody></table>';

                $this->sdc->PDFLayout($reportName,$html);
    }

    public function getPOSPaymentStatusPaypal(Request $request,$invoice_id=0,$status='fahad')
    {
        //dd($invoice_id);
        $payment_id=\Session::get('paypal_payment_id');
                    \Session::forget('paypal_payment_id');

        if(empty($request->PayerID) || empty($request->token))
        {
            \Session::put('error','Failed token mismatch, Please tryagain');
            return redirect('pos');
        }

        $payment=Payment::get($payment_id,$this->_api_content);
        $excution=new PaymentExecution();
        $excution->setPayerId($request->PayerID);

        $result=$payment->execute($excution,$this->_api_content);
        //dd($invoice_id);
        if($result->getState()=='approved')
        {
            $trans=$result->getTransactions();
            //$amtAr=$trans->getAmount();
            $amountPaid=$trans[0]->getAmount()->getTotal();
            //dd($amountPaid);

            $tenderData=Tender::where('paypal',1)->first();

            $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
            $invoiceData->tender_id=$tenderData->id;
            $invoiceData->tender_name=$tenderData->name;
            $invoiceData->invoice_status='Paid';
            $total_profit=$invoiceData->total_amount-$invoiceData->total_cost;
            $invoiceData->total_profit=$total_profit;
            $invoiceData->save();

            //dd($invoiceData);

            $cusInfo=Customer::find($invoiceData->customer_id);

            $invoicePay=new InvoicePayment;
            $invoicePay->invoice_id=$invoice_id;
            $invoicePay->customer_id=$invoiceData->customer_id;
            $invoicePay->customer_name=$cusInfo->name;
            $invoicePay->tender_id=$tenderData->id;
            $invoicePay->tender_name=$tenderData->name;
            $invoicePay->total_amount=$invoiceData->total_amount;
            $invoicePay->paid_amount=$amountPaid;
            $invoicePay->store_id=$invoiceData->store_id;
            $invoicePay->created_by=$invoiceData->created_by;
            $invoicePay->save();

            $cart = Session::has('Pos') ? Session::get('Pos') : null;
            $Ncart = new Pos($cart);
            $Ncart->ClearCart();
            Session::put('Pos', $Ncart);
            
            \Session::put('success','Paypal payment successfully accepted.');
            return redirect('pos'); die();
        }
        else
        {
            \Session::put('error','Payment Failed, Please tryagain');
           return redirect('pos'); die();
        }

    }

    public function getPOSPartialPaymentStatusPaypal(Request $request,$invoice_id=0,$payment_id=0,$paid_amount=0,$status='fahad')
    {
        //dd($invoice_id);
        $payment_id=\Session::get('paypal_payment_id');
                    \Session::forget('paypal_payment_id');

        if(empty($request->PayerID) || empty($request->token))
        {
            \Session::put('error','Failed token mismatch, Please tryagain');
            return redirect('pos');
        }

        $payment=Payment::get($payment_id,$this->_api_content);
        $excution=new PaymentExecution();
        $excution->setPayerId($request->PayerID);

        $result=$payment->execute($excution,$this->_api_content);
        //dd($invoice_id);
        if($result->getState()=='approved')
        {
            $trans=$result->getTransactions();
            //$amtAr=$trans->getAmount();
            $amountPaid=$trans[0]->getAmount()->getTotal();
            //dd($amountPaid);

            $tenderData=Tender::where('paypal',1)->first();

            $loadInvoices=Invoice::join('customers','invoices.customer_id','=','customers.id')
                                  ->select(
                                    'invoices.id',
                                    'invoices.invoice_id',
                                    'invoices.total_amount',
                                    'invoices.paid_amount',
                                    'customers.name as customer_name',
                                    \DB::Raw('CASE WHEN lsp_invoices.paid_amount = 0 THEN 
                                        (SELECT SUM(paid_amount) FROM lsp_invoice_payments WHERE lsp_invoice_payments.invoice_id=lsp_invoices.invoice_id)
                                    ELSE lsp_invoices.paid_amount END AS absPaid'),
                                    'invoices.created_at')
                                  ->where('invoices.store_id',$this->sdc->storeID())
                                  ->where('invoices.invoice_id',$invoice_id)
                                  ->whereRaw("lsp_invoices.invoice_status!='Paid'")
                                  ->first();

            $invoice=Invoice::where('invoice_id',$invoice_id)->first();
            //dd($invoiceData);



            $cusInfo=Customer::find($invoice->customer_id);

            $load_total_amount=$loadInvoices->total_amount;
            $load_absPaid=$loadInvoices->absPaid+$paid_amount;
            $load_due=$load_total_amount-$load_absPaid;
            if($load_due>0)
            {
                $load_invoice_status="Partial";
            }
            elseif($load_due<=0)
            {
                $load_invoice_status="Paid";
                $load_due="0.00";
            }
            else
            {
                $load_invoice_status="Partial";
            }

            if(empty($invoice->tender_id))
            {
                $tender=Tender::find($request->payment_method_id);
                $tender_name=$tender->name;
                $tender_id=$tender->id;

                $invoice->tender_id=$tender_id;
                $invoice->tender_name=$tender_name;
                $invoice->save();
            }
            else
            {
                $tender_id=$invoice->tender_id;
                $tender=Tender::find($tender_id);
                $tender_name=$tender->name;
                $invoice->save();
            }

            $chkTicketInvoice=\DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->count();
            if($chkTicketInvoice>0)
            {
                \DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }

            $chkRepairInvoice=\DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->count();
            if($chkRepairInvoice>0)
            {
                \DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }


            $invoicePay=new InvoicePayment;
            $invoicePay->invoice_id=$invoice_id;
            $invoicePay->customer_id=$invoice->customer_id;
            $invoicePay->customer_name=$cusInfo->name;
            $invoicePay->tender_id=$tenderData->id;
            $invoicePay->tender_name=$tenderData->name;
            $invoicePay->total_amount=$invoice->total_amount;
            $invoicePay->paid_amount=$amountPaid;
            $invoicePay->store_id=$this->sdc->storeID();
            $invoicePay->created_by=$this->sdc->UserID();
            $invoicePay->save();

            if($load_due<=0)
            {
                $invoice->due_amount="0.00";
            }
            
            $invoice->invoice_status=$load_invoice_status;
            $invoice->save();
            
            \Session::put('success','Paypal Partial payment successfully accepted.');
            return redirect('pos'); die();
        }
        else
        {
            \Session::put('error','Failed To Accept Partial Payment, Please try again');
           return redirect('pos'); die();
        }

    }


    public function getCounterPOSPaymentStatusPaypal(Request $request,$invoice_id=0,$status='fahad')
    {
        //dd($invoice_id);
        $payment_id=\Session::get('paypal_payment_id');
                    \Session::forget('paypal_payment_id');

        if(empty($request->PayerID) || empty($request->token))
        {
            \Session::put('error','Failed token mismatch, Please tryagain');
            return redirect('counter-display');
        }

        $payment=Payment::get($payment_id,$this->_api_content);
        $excution=new PaymentExecution();
        $excution->setPayerId($request->PayerID);

        $result=$payment->execute($excution,$this->_api_content);
        //dd($invoice_id);
        if($result->getState()=='approved')
        {
            $trans=$result->getTransactions();
            //$amtAr=$trans->getAmount();
            $amountPaid=$trans[0]->getAmount()->getTotal();
            //dd($amountPaid);

            $tenderData=Tender::where('paypal',1)->first();

            $invoiceData=Invoice::where('invoice_id',$invoice_id)->orderBy('id','DESC')->first();
            $invoiceData->tender_id=$tenderData->id;
            $invoiceData->tender_name=$tenderData->name;
            $invoiceData->invoice_status='Paid';
            $total_profit=$invoiceData->total_amount-$invoiceData->total_cost;
            $invoiceData->total_profit=$total_profit;
            $invoiceData->save();

            //dd($invoiceData);

            $cusInfo=Customer::find($invoiceData->customer_id);

            //dd($cusInfo);

            $invoicePay=new InvoicePayment;
            $invoicePay->invoice_id=$invoice_id;
            $invoicePay->customer_id=$invoiceData->customer_id;
            $invoicePay->customer_name=$cusInfo->name;
            $invoicePay->tender_id=$tenderData->id;
            $invoicePay->tender_name=$tenderData->name;
            $invoicePay->total_amount=$invoiceData->total_amount;
            $invoicePay->paid_amount=$amountPaid;
            $invoicePay->store_id=$invoiceData->store_id;
            $invoicePay->created_by=$invoiceData->created_by;
            $invoicePay->save();

            $invoiceSalesAmount=0;
            $cart=[];
            $counterDisplayID=CounterDisplay::select('session_id')
                             ->where('user_id',\Auth::user()->id)
                             ->first();
            if(isset($counterDisplayID))
            {
                $datas=\DB::table('sessions')->where('id',trim($counterDisplayID->session_id))
                            ->where('user_id',\Auth::user()->id)
                            ->first();

                if(isset($datas))
                {
                    if(isset($datas->payload))
                    {
                        $data=unserialize(base64_decode($datas->payload));
                        //dd($data['Pos']);
                        $cart=$data['Pos'];
                        $invoiceSalesAmount=1;
                    }
                }
            }

            $data['Pos']->paid=$data['Pos']->paid+$amountPaid;
            $dataPayload=base64_encode(serialize($data));

            \DB::table('sessions')
                            ->where('id',trim($counterDisplayID->session_id))
                            ->where('user_id',\Auth::user()->id)
                            ->update(['payload'=>$dataPayload]);

            //die();

            //;
            //dd($data);

            $cart = Session::has('Pos') ? Session::get('Pos') : null;
            $Ncart = new Pos($cart);
            $Ncart->ClearCart();
            Session::put('Pos', $Ncart);
            
            \Session::put('success','Paypal payment successfully accepted.');
            return redirect('counter-display'); die();
        }
        else
        {
            \Session::put('error','Payment Failed, Please tryagain');
           return redirect('counter-display'); die();
        }

    }



    public function paywithpaypalInvoice(Invoice $invoice,$invoice_id=0)
    {
        if(!empty($invoice_id))
        {



            $tab_invoice=$invoice::Leftjoin('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          "tenders.name as tender",
                                          'invoices.store_id',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.invoice_id',$invoice_id)
                                 ->first();
            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 //->groupBy("invoice_id")
                                 ->sum('paid_amount');

            //print_r($invoice_payment);   die();                  

            $tab_customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               ->select('invoice_products.*','products.name as product_name')
                                               ->get();

            $chkEmailInvoice=AuthorizeNetPayment::where('store_id',$tab_invoice->store_id)
                                                ->where('active_module_for_email_invoice',1)
                                                ->count();

            $chkAuthorizeNetPayment=AuthorizeNetPayment::where('store_id',$tab_invoice->store_id)
                                                ->where('active_module_for_email_invoice',1)
                                                ->count();

            //echo $tab_invoice->store_id; die();
            $authorizeNettender=Tender::where('authorizenet',1)->get();
            $payPaltender=Tender::where('paypal',1)->get();
            $InvInfo=$this->sdc->Invlayout($tab_invoice->store_id);
            
            $invoice_due_amount=number_format(($tab_invoice->total_amount-$invoice_payment),2);

            //echo $invoice_due_amount; die();


            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            $item1 = new Item();
            $item1->setName('Invoice - '.$invoice_id)
                ->setCurrency('USD')
                ->setQuantity(1)
                ->setSku($tab_invoice->id) // Similar to `item_number` in Classic API
                ->setPrice($invoice_due_amount);
            


            $itemList = new ItemList();
            $itemList->setItems(array($item1));   

            $details = new Details();
            $details->setSubtotal($invoice_due_amount); 

            $amount = new Amount();
            $amount->setCurrency("USD")
                ->setTotal($invoice_due_amount)
                ->setDetails($details);   
            
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Invoice Payment description")
                ->setInvoiceNumber(uniqid()); 

            //$baseUrl = url();
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(url('invoice/payment/paypal/'.$invoice_id.'/success'))
                ->setCancelUrl(url('invoice/payment/paypal/'.$invoice_id.'/cancel'));

            $payment = new Payment();
            $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));


            try {
                $payment->create($this->_api_content);
            } catch (\PayPal\Exception\PPConnectionException $ex) {

                dd($ex);
                if(\Config::get('app.debug'))
                {
                    \Session::put('error','Connection has timeout.!!!!, Please try again.');
                    return redirect('invoice/pay/'.$invoice_id);
                }
                else
                {
                    \Session::put('error','Something went wrong.!!!!, Please try again.');
                    return redirect('invoice/pay/'.$invoice_id);
                }
            }


            foreach($payment->getLinks() as $link){
                if($link->getRel()=='approval_url')
                {
                    $redirect_url=$link->getHref();
                    break;
                }
            }

            \Session::put('paypal_payment_id',$payment->getId());

            if(isset($redirect_url))
            {
                return redirect($redirect_url);
            }

            \Session::put('error','Unknown error occured, Please try again.!!!!!');
            return redirect('invoice/pay/'.$invoice_id);
            
        }
        else
        {
            return redirect('invoice/pay/'.$invoice_id)->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }
    }

    public function posPayPaypal()
    {
       $cart = Session::has('Pos') ? Session::get('Pos') : null;
       $countItems=count($cart->items);
       $total_amount_invoice=0;
       $total_cost_invoice=0;
       $total_profit_invoice=0;
       $total_sold_quantity=0;

       $discount_type=0;
       $discount_amount=0;
       $discount_total=0;

       if($countItems>0)
       {
            $invoice_id=$cart->invoiceID;
            if(empty($invoice_id))
            {
                $invoice_id=time();
            }

            foreach($cart->items as $row):
                $pid=$row['item_id'];
                $quantity=$row['qty'];
                $unitprice=$row['unitprice'];
                $pro=Product::find($pid);
                $tab_stock=new InvoiceProduct;
                $tab_stock->invoice_id=$invoice_id;
                $tab_stock->product_id=$pid;
                $tab_stock->tax_percent=$cart->TaxRate;
                $tab_stock->tax_amount=$row['tax'];
                $tab_stock->quantity=$quantity;
                $tab_stock->price=$unitprice;
                $tab_stock->cost=$pro->cost;
                $tab_stock->total_price=($quantity*$unitprice);
                $tab_stock->total_cost=($quantity*$pro->cost);
                $tab_stock->store_id=$this->sdc->storeID();
                $tab_stock->created_by=$this->sdc->UserID();
                $tab_stock->save();

                Product::where('id',$pid)
                ->update([
                   'quantity' => \DB::raw('quantity - '.$quantity),
                   'sold_times' => \DB::raw('sold_times + 1')
                ]);

                $amount_invoice=($quantity*$unitprice);
                $cost_invoice=($quantity*$pro->cost);
                $profit_invoice=$amount_invoice-$cost_invoice;
                $total_amount_invoice+=$amount_invoice;
                $total_cost_invoice+=$cost_invoice;
                $total_profit_invoice+=$profit_invoice;
                $total_sold_quantity+=$quantity;
            endforeach;

            
            $discount_type=$cart->discount_type;
            $discount_amount=$cart->sales_discount;
            if(!empty($discount_type))
            {
                if(!empty($discount_amount))
                {
                    if($discount_type==1)
                    {
                        $discount_total=$discount_amount;
                    }
                    elseif($discount_type==2)
                    {
                        $discount_total=(($total_amount_invoice*$discount_amount)/100);
                    }
                }
            }

            

            $taxAmount=(($total_amount_invoice*$cart->TaxRate)/100);
            $total_amount_invoice-=$discount_total;
            $total_amount_invoice+=$taxAmount;

            $sqlTender=Tender::find($cart->paymentMethodID);
            $tender_name="";
            $invoiceStatus="Due";
            if(isset($sqlTender))
            {
                $tender_name=$sqlTender->name?$sqlTender->name:'';
                if(isset($cart->paid))
                {
                    if(!empty($cart->paid))
                    {
                        if($total_amount_invoice>$cart->paid)
                        {
                            $invoiceStatus="Partial";
                        }
                        elseif($total_amount_invoice==$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                        elseif($total_amount_invoice<=$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                    }
                    
                }
                
            }

            $tab=new Invoice;
            $tab->invoice_id=$invoice_id;
            $tab->customer_id=$cart->customerID;
            $tab->tender_id=$cart->paymentMethodID;
            $tab->tender_name=$tender_name;
            $tab->invoice_status=$invoiceStatus;
            $tab->tax_rate=$cart->TaxRate;
            $tab->total_tax=$taxAmount;
            $tab->discount_type=$discount_type;
            $tab->sales_discount=$discount_amount;
            $tab->discount_total=$discount_total;
            $tab->total_amount=$total_amount_invoice;
            $tab->total_cost=$total_cost_invoice;
            $tab->total_profit=$total_profit_invoice;
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
            $nid=$tab->id;

            $tabCus=Customer::find($cart->customerID);
            $tabCus->last_invoice_no=$invoice_id;
            $tabCus->save();
            $customer_name=$tabCus->name;

            

            /*$tabInPay=new InvoicePayment;
            $tabInPay->invoice_id=$invoice_id;
            $tabInPay->customer_id=$cart->customerID;
            $tabInPay->customer_name=$customer_name;
            $tabInPay->tender_id=$cart->paymentMethodID;
            $tabInPay->tender_name=$tender_name;
            $tabInPay->total_amount=$total_amount_invoice;
            $tabInPay->paid_amount=$cart->paid;
            $tabInPay->store_id=$this->sdc->storeID();
            $tabInPay->created_by=$this->sdc->UserID();
            $tabInPay->save();*/

            $this->sdc->log("sales","Invoice Created, Invoice ID : ".$invoice_id);

            RetailPosSummary::where('id',1)
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
               'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice),
               'product_quantity' => \DB::raw('product_quantity - '.$total_sold_quantity)
            ]);

            $Todaydate=date('Y-m-d');
            if(RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==0)
            {
                RetailPosSummaryDateWise::insert([
                   'report_date'=>$Todaydate,
                   'sales_invoice_quantity' => \DB::raw('1'),
                   'sales_quantity' => \DB::raw($total_sold_quantity),
                   'sales_amount' => \DB::raw($total_amount_invoice),
                   'sales_cost' => \DB::raw($total_cost_invoice),
                   'sales_profit' => \DB::raw($total_profit_invoice)
                ]);
            }
            else
            {
                RetailPosSummaryDateWise::where('report_date',$Todaydate)
                ->update([
                   'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
                   'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
                   'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
                   'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
                   'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice)
                ]);
            }

            $edQr=$this->sdc->invoiceEmailTemplate();
            $emaillayoutData=$edQr['editData'];
            $bcc=$emaillayoutData->bcc?$emaillayoutData->bcc:'';

            $tabsse=new SendSalesEmail;
            $tabsse->invoice_id=$invoice_id;
            $tabsse->email_address=$tabCus->email;
            $tabsse->bcc_email_address=$bcc;
            $tabsse->email_process_type=$emaillayoutData->email_time;
            $tabsse->store_id=$this->sdc->storeID();
            $tabsse->created_by=$this->sdc->UserID();
            $tabsse->save();

            
            
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            $item1 = new Item();
            $item1->setName('Invoice - '.$invoice_id)
                    ->setCurrency('USD')
                    ->setQuantity(1)
                    ->setSku($nid) // Similar to `item_number` in Classic API
                    ->setPrice($total_amount_invoice);
            


            $itemList = new ItemList();
            $itemList->setItems(array($item1));   

            $details = new Details();
            $details->setSubtotal($total_amount_invoice); 

            $amount = new Amount();
            $amount->setCurrency("USD")
                    ->setTotal($total_amount_invoice)
                    ->setDetails($details);   
            
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Invoice Payment description")
                ->setInvoiceNumber(uniqid()); 

            //$baseUrl = url();
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(url('pos/payment/paypal/'.$invoice_id.'/success'))
                ->setCancelUrl(url('pos/payment/paypal/'.$invoice_id.'/cancel'));

            $payment = new Payment();
            $payment->setIntent("sale")
                    ->setPayer($payer)
                    ->setRedirectUrls($redirectUrls)
                    ->setTransactions(array($transaction));


            try {
                $payment->create($this->_api_content);
            } catch (\PayPal\Exception\PPConnectionException $ex) {

                dd($ex);
                if(\Config::get('app.debug'))
                {
                    \Session::put('error','Connection has timeout.!!!!, Please try again.');
                    return redirect('pos');
                }
                else
                {
                    \Session::put('error','Something went wrong.!!!!, Please try again.');
                    return redirect('pos');
                }
            }


            foreach($payment->getLinks() as $link){
                if($link->getRel()=='approval_url')
                {
                    $redirect_url=$link->getHref();
                    break;
                }
            }

            \Session::put('paypal_payment_id',$payment->getId());

            if(isset($redirect_url))
            {
                return redirect($redirect_url);
            }

            \Session::put('error','Unknown error occured, Please try again.!!!!!');
            return redirect('pos');
            
       }
       else
       {
            \Session::put('error','No item in cart, Please try again.!!!!!');
            return redirect('pos');
       }
    }

    public function partialPayPaypal($invoice_id=0,$payment_id=0,$paid_amount=0)
    {


        $nidInfo=Invoice::where('invoice_id',$invoice_id)->first();
        $nid=$nidInfo->id;  
            
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            $item1 = new Item();
            $item1->setName('Invoice - '.$invoice_id)
                    ->setCurrency('USD')
                    ->setQuantity(1)
                    ->setSku($nid) // Similar to `item_number` in Classic API
                    ->setPrice($paid_amount);
            


            $itemList = new ItemList();
            $itemList->setItems(array($item1));   

            $details = new Details();
            $details->setSubtotal($paid_amount); 

            $amount = new Amount();
            $amount->setCurrency("USD")
                    ->setTotal($paid_amount)
                    ->setDetails($details);   
            
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Invoice Partial Payment description")
                ->setInvoiceNumber(uniqid()); 

            //$baseUrl = url();
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(url('partial/payment/paypal/'.$invoice_id.'/'.$payment_id.'/'.$paid_amount.'/success'))
                ->setCancelUrl(url('partial/payment/paypal/'.$invoice_id.'/'.$payment_id.'/'.$paid_amount.'/cancel'));

            $payment = new Payment();
            $payment->setIntent("sale")
                    ->setPayer($payer)
                    ->setRedirectUrls($redirectUrls)
                    ->setTransactions(array($transaction));


            try {

                $payment->create($this->_api_content);
            } catch (\PayPal\Exception\PPConnectionException $ex) {

                dd($ex);
                if(\Config::get('app.debug'))
                {
                    \Session::put('error','Connection has timeout.!!!!, Please try again.');
                    return redirect('pos');
                }
                else
                {
                    \Session::put('error','Something went wrong.!!!!, Please try again.');
                    return redirect('pos');
                }
            }


            foreach($payment->getLinks() as $link){
                if($link->getRel()=='approval_url')
                {
                    $redirect_url=$link->getHref();
                    break;
                }
            }

            \Session::put('paypal_payment_id',$payment->getId());

            if(isset($redirect_url))
            {
                return redirect($redirect_url);
            }

            \Session::put('error','Unknown error occured, Please try again.!!!!!');
            return redirect('pos');
    }

    public function posCounterPayPaypal()
    {
        $invoiceSalesAmount=0;
        $cart=[];
        $counterDisplayID=CounterDisplay::select('session_id')
                         ->where('user_id',\Auth::user()->id)
                         ->first();
        if(isset($counterDisplayID))
        {
            $datas=\DB::table('sessions')->where('id',trim($counterDisplayID->session_id))
                        ->where('user_id',\Auth::user()->id)
                        ->first();

            if(isset($datas))
            {
                if(isset($datas->payload))
                {
                    //dd($datas->payload);
                    $data=unserialize(base64_decode($datas->payload));
                    //dd($data['Pos']);
                    $cart=$data['Pos'];
                    $invoiceSalesAmount=1;
                }
            }
        }

        if($invoiceSalesAmount==0)
        {
            \Session::put('error','No Item In Shopping Cart.!!!!!');
            return redirect('counter-display');
        }

        if(empty($cart->customerID))
        {
             \Session::put('error','Customer info not saved yet.!!!!!');
             return redirect('counter-display');
        }



        /*$data['Pos']->paid=10;

        //dd($data);

        $paidArray=base64_encode(serialize($data));
       // $paidArray=session_decode($datas->payload);

        dd($paidArray);

        die();*/

       //$cart = Session::has('Pos') ? Session::get('Pos') : null;
       $countItems=count($cart->items);
       $total_amount_invoice=0;
       $total_cost_invoice=0;
       $total_profit_invoice=0;
       $total_sold_quantity=0;

       $discount_type=0;
       $discount_amount=0;
       $discount_total=0;

       if($countItems>0)
       {
            $invoice_id=$cart->invoiceID;
            if(empty($invoice_id))
            {
                $invoice_id=time();
            }

            foreach($cart->items as $row):
                $pid=$row['item_id'];
                $quantity=$row['qty'];
                $unitprice=$row['unitprice'];
                $pro=Product::find($pid);
                $tab_stock=new InvoiceProduct;
                $tab_stock->invoice_id=$invoice_id;
                $tab_stock->product_id=$pid;
                $tab_stock->tax_percent=$cart->TaxRate;
                $tab_stock->tax_amount=$row['tax'];
                $tab_stock->quantity=$quantity;
                $tab_stock->price=$unitprice;
                $tab_stock->cost=$pro->cost;
                $tab_stock->total_price=($quantity*$unitprice);
                $tab_stock->total_cost=($quantity*$pro->cost);
                $tab_stock->store_id=$this->sdc->storeID();
                $tab_stock->created_by=$this->sdc->UserID();
                $tab_stock->save();

                Product::where('id',$pid)
                ->update([
                   'quantity' => \DB::raw('quantity - '.$quantity),
                   'sold_times' => \DB::raw('sold_times + 1')
                ]);

                $amount_invoice=($quantity*$unitprice);
                $cost_invoice=($quantity*$pro->cost);
                $profit_invoice=$amount_invoice-$cost_invoice;
                $total_amount_invoice+=$amount_invoice;
                $total_cost_invoice+=$cost_invoice;
                $total_profit_invoice+=$profit_invoice;
                $total_sold_quantity+=$quantity;
            endforeach;

            
            $discount_type=$cart->discount_type;
            $discount_amount=$cart->sales_discount;
            if(!empty($discount_type))
            {
                if(!empty($discount_amount))
                {
                    if($discount_type==1)
                    {
                        $discount_total=$discount_amount;
                    }
                    elseif($discount_type==2)
                    {
                        $discount_total=(($total_amount_invoice*$discount_amount)/100);
                    }
                }
            }

            

            $taxAmount=(($total_amount_invoice*$cart->TaxRate)/100);
            $total_amount_invoice-=$discount_total;
            $total_amount_invoice+=$taxAmount;

            $sqlTender=Tender::find($cart->paymentMethodID);
            $tender_name="";
            $invoiceStatus="Due";
            if(isset($sqlTender))
            {
                $tender_name=$sqlTender->name?$sqlTender->name:'';
                if(isset($cart->paid))
                {
                    if(!empty($cart->paid))
                    {
                        if($total_amount_invoice>$cart->paid)
                        {
                            $invoiceStatus="Partial";
                        }
                        elseif($total_amount_invoice==$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                        elseif($total_amount_invoice<=$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                    }
                    
                }
                
            }

            $tab=new Invoice;
            $tab->invoice_id=$invoice_id;
            $tab->customer_id=$cart->customerID;
            $tab->tender_id=$cart->paymentMethodID;
            $tab->tender_name=$tender_name;
            $tab->invoice_status=$invoiceStatus;
            $tab->tax_rate=$cart->TaxRate;
            $tab->total_tax=$taxAmount;
            $tab->discount_type=$discount_type;
            $tab->sales_discount=$discount_amount;
            $tab->discount_total=$discount_total;
            $tab->total_amount=$total_amount_invoice;
            $tab->total_cost=$total_cost_invoice;
            $tab->total_profit=$total_profit_invoice;
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
            $nid=$tab->id;

            $tabCus=Customer::find($cart->customerID);
            $tabCus->last_invoice_no=$invoice_id;
            $tabCus->save();
            $customer_name=$tabCus->name;

            

            /*$tabInPay=new InvoicePayment;
            $tabInPay->invoice_id=$invoice_id;
            $tabInPay->customer_id=$cart->customerID;
            $tabInPay->customer_name=$customer_name;
            $tabInPay->tender_id=$cart->paymentMethodID;
            $tabInPay->tender_name=$tender_name;
            $tabInPay->total_amount=$total_amount_invoice;
            $tabInPay->paid_amount=$cart->paid;
            $tabInPay->store_id=$this->sdc->storeID();
            $tabInPay->created_by=$this->sdc->UserID();
            $tabInPay->save();*/

            $this->sdc->log("sales","Invoice Created, Invoice ID : ".$invoice_id);

            RetailPosSummary::where('id',1)
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
               'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice),
               'product_quantity' => \DB::raw('product_quantity - '.$total_sold_quantity)
            ]);

            $Todaydate=date('Y-m-d');
            if(RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==0)
            {
                RetailPosSummaryDateWise::insert([
                   'report_date'=>$Todaydate,
                   'sales_invoice_quantity' => \DB::raw('1'),
                   'sales_quantity' => \DB::raw($total_sold_quantity),
                   'sales_amount' => \DB::raw($total_amount_invoice),
                   'sales_cost' => \DB::raw($total_cost_invoice),
                   'sales_profit' => \DB::raw($total_profit_invoice)
                ]);
            }
            else
            {
                RetailPosSummaryDateWise::where('report_date',$Todaydate)
                ->update([
                   'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
                   'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
                   'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
                   'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
                   'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice)
                ]);
            }

            $edQr=$this->sdc->invoiceEmailTemplate();
            $emaillayoutData=$edQr['editData'];
            $bcc=$emaillayoutData->bcc?$emaillayoutData->bcc:'';

            $tabsse=new SendSalesEmail;
            $tabsse->invoice_id=$invoice_id;
            $tabsse->email_address=$tabCus->email;
            $tabsse->bcc_email_address=$bcc;
            $tabsse->email_process_type=$emaillayoutData->email_time;
            $tabsse->store_id=$this->sdc->storeID();
            $tabsse->created_by=$this->sdc->UserID();
            $tabsse->save();

            
            
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");

            $item1 = new Item();
            $item1->setName('Invoice - '.$invoice_id)
                    ->setCurrency('USD')
                    ->setQuantity(1)
                    ->setSku($nid) // Similar to `item_number` in Classic API
                    ->setPrice($total_amount_invoice);
            


            $itemList = new ItemList();
            $itemList->setItems(array($item1));   

            $details = new Details();
            $details->setSubtotal($total_amount_invoice); 

            $amount = new Amount();
            $amount->setCurrency("USD")
                    ->setTotal($total_amount_invoice)
                    ->setDetails($details);   
            
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Invoice Payment description")
                ->setInvoiceNumber(uniqid()); 

            //$baseUrl = url();
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(url('counter-pos/payment/paypal/'.$invoice_id.'/success'))
                ->setCancelUrl(url('counter-pos/payment/paypal/'.$invoice_id.'/cancel'));

            $payment = new Payment();
            $payment->setIntent("sale")
                    ->setPayer($payer)
                    ->setRedirectUrls($redirectUrls)
                    ->setTransactions(array($transaction));


            try {
                $payment->create($this->_api_content);
            } catch (\PayPal\Exception\PPConnectionException $ex) {

                //dd($ex);
                if(\Config::get('app.debug'))
                {
                    \Session::put('error','Connection has timeout.!!!!, Please try again.');
                    return redirect('counter-display');
                }
                else
                {
                    \Session::put('error','Something went wrong.!!!!, Please try again.');
                    return redirect('counter-display');
                }
            }


            foreach($payment->getLinks() as $link){
                if($link->getRel()=='approval_url')
                {
                    $redirect_url=$link->getHref();
                    break;
                }
            }

            \Session::put('paypal_payment_id',$payment->getId());

            if(isset($redirect_url))
            {
                return redirect($redirect_url);
            }

            \Session::put('error','Unknown error occured, Please try again.!!!!!');
            return redirect('counter-display');
            
       }
       else
       {
            \Session::put('error','No item in cart, Please try again.!!!!!');
            return redirect('counter-display');
       }
    }

    public function openStore(Request $request)
    {
        $tabCount=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->count();

        if($tabCount>0)
        {
            return response()->json('Already Open, Please reload page....');
            exit();
        }
        else
        {
            $tab=new OpenDrawer();
            $tab->opening_amount=$request->openStoreBalance;
            $tab->store_status='Open';
            $tab->opening_time=date('Y-m-d H:i:s');
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
            $openDI=$tab->id;

            return response()->json($openDI);
        }

        

    }

    public function slide(Request $request)
    {
        if(!session::has('slide'))
        {
            session::put('slide',1);
        }
        elseif(session::has('slide'))
        {
            if(session::get('slide')==1)
            {
                session::put('slide',2);
            }
            else
            {
                session::put('slide',1);
            }
            
        }

        $newLimit=session::get('slide')?session::get('slide'):1;

        return $newLimit;
    }


    public function closeStore()
    {
        $tabCount=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->count();
        if($tabCount>0)
        {
            $storeInfo=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->orderBy('id','DESC')
                            ->first();

            $getStoreDateTime=$storeInfo->opening_time;
            if(empty($storeInfo->opening_time))
            {
                $getStoreDateTime=$storeInfo->created_at;
            }
            $opening_amount=$storeInfo->opening_amount;
            $getStoreCloseDateTime=date('Y-m-d H:i:s');

            $totalSales=Invoice::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('total_amount');

            $totalTax=Invoice::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('total_tax');

            $totalSalesTender=Invoice::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->select('tender_id','tender_name',\DB::Raw('SUM(total_amount) as tender_total'))
                            ->groupBy('tender_id')
                            ->orderBy('tender_name','ASC')
                            ->get();

            $totalPayoutPlus=Payout::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('amount');

            $totalPayoutMin=Payout::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('negative_amount');

            //dd($totalPayoutMin);

            $totalPayout=$totalPayoutPlus-$totalPayoutMin;

           // dd($totalSalesTender);

            $array=array('status'=>1,'opening_amount'=>$opening_amount,'opening_time'=>date('d/m/Y',strtotime($getStoreDateTime)),'salesTotal'=>$totalSales,'totalSalesTender'=>$totalSalesTender,'totalTax'=>$totalTax);

            $closing_amount=$totalSales+$opening_amount+$totalPayout;

            $userFullName=\Auth::user()->name;

            $tabClStr=new CloseDrawer();
            $tabClStr->cashier_id=$this->sdc->UserID();
            $tabClStr->cashier_name=$userFullName;
            $tabClStr->opeing_time=$getStoreDateTime;
            $tabClStr->opening_amount=$opening_amount;
            $tabClStr->closing_amount=$closing_amount;
            $tabClStr->closing_time=$getStoreCloseDateTime;
            $tabClStr->store_id=$this->sdc->storeID();
            $tabClStr->created_by=$this->sdc->UserID();
            $tabClStr->save();

            $clstrID=$tabClStr->id;

            $storeInfo->store_status='Close';
            $storeInfo->store_closing_id=$clstrID;
            $storeInfo->save();
            
        }
        else
        {
            $array=array('status'=>0);
            $clstrID=0;
        }

        



        return response()->json($clstrID);


    }



    public function printCloseStore($closing_id=0)
    {
            
        $closeStoreData=CloseDrawer::find($closing_id);

        $getStoreDateTime=$closeStoreData->opeing_time;
        $getStoreCloseDateTime=$closeStoreData->closing_time;
        $opening_amount=$closeStoreData->opening_amount;

        $totalSales=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('total_amount');

        $totalTax=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('total_tax');

        $totalSalesTender=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->select('tender_id','tender_name',\DB::Raw('SUM(total_amount) as tender_total'))
                        ->groupBy('tender_id')
                        ->orderBy('tender_name','ASC')
                        ->get();

        $totalPayoutPlus=Payout::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('amount');

        $totalPayoutMin=Payout::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('negative_amount');

        $totalPayout=$totalPayoutPlus-$totalPayoutMin;
        $array=array('status'=>1,'opening_amount'=>$opening_amount,'opening_time'=>date('d/m/Y',strtotime($getStoreDateTime)),'salesTotal'=>$totalSales,'totalSalesTender'=>$totalSalesTender,'totalTax'=>$totalTax);

        $closing_amount=$totalSales+$opening_amount+$totalPayout;

        $userFullName=$closeStoreData->cashier_name;

        $invInfo=$this->sdc->Invlayout($closeStoreData->store_id);
        if(!file_exists('company/'.$invInfo->logo))
        {
            return redirect()->back()->with('error', ' Invoice failed to load, Please Set Invoice/Report Logo. !');
        }

        $address='';
        if(isset($invInfo->address))
        {
            $address=$invInfo->address;
        }

        if(isset($invInfo->mm_four))
        {
            $address=$invInfo->mm_four;
        }

        $html='';

        $html .='<table align="center" width="100%">
                    <tbody>
                        <tr>
                            <td align="center">
                                <img class="logo" height="45" src="'.url('company/'.$invInfo->logo).'" alt="brand logo"/>
                            </td>
                        </tr>
                        <tr>
                            <td align="center">'.$invInfo->company_name.'</td>
                        </tr>
                        <tr>
                            <td align="center">'.$invInfo->c_one.'</td>
                        </tr>
                        <tr>
                            <td align="center">'.$address.'</td>
                        </tr>
                    </tbody>
                </table>';

                    $html .='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td colspan="4"><hr></td>
                                        </tr>
                                        <tr>
                                            <td>Cashier</td>
                                            <td colspan="3">: '.$closeStoreData->cashier_name.'</td>
                                        </tr>
                                        <tr>
                                            <td>Date Time</td>
                                            <td colspan="3">: '.formatDateTime($getStoreDateTime).'</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><hr></td>
                                        </tr>
                                    </tbody>
                                </table>';

        $html .='<h4 align="center"><strong>DRAWER CLOSING DETAIL </strong></h4>';

        $html .='<table class="table table-striped table-bordered">
                <tbody>
                  <tr>
                      <td align="left" width="80%">Opening Time  </td>
                      <td align="left" id="storeCloseTotalCollection">';
                      $html .=$getStoreDateTime;
                      $html .='</td>
                  </tr>
                  <tr>
                      <td align="left" width="80%">Closing Time </td>
                      <td align="left" id="storeCloseTotalCollection">';
                      $html .=$getStoreCloseDateTime;
                      $html .='</td>
                  </tr>
                  <tr>
                      <td colspan="2"><div style="background:rgba(51,51,51,1); display:block; height:1px;"></div> </td>
                  </tr>
                  <tr>
                      <td align="left" width="80%">Total Collection :  </td>
                      <td align="left" id="storeCloseTotalCollection">$';
                      $html .=number_format($totalSales,2);
                      $html .='</td>
                  </tr>
                </tbody>
                <tbody id="storeCloseTableTenderList">';

                  if(isset($totalSalesTender))
                  {
                        foreach($totalSalesTender as $row)
                        {
                            $html .='<tr>
                                          <td align="left">'.$row->tender_name.' Collected (+) :  </td>
                                          <td align="left">$';
                                          $html .=number_format($row->tender_total,2);
                                          $html .='</td>
                                      </tr>';
                        }
                  }


                  $html .='
                </tbody>
                <tbody>
                  <tr>
                      <td align="left">Opening Amount (+) :  </td>
                      <td align="left">$<span id="storeCloseOpeningAmount">';
                      $html .=number_format($opening_amount,2);
                      $html .='</span></td>
                  </tr>
                  <tr>
                      <td align="left">Payout (+)(-) :  </td>
                      <td align="left">$<span id="totalPayout">';
                      $html .=number_format($totalPayout,2);
                      $html .='</span></td>
                  </tr>';
                  //<tr><td align="left">BuyBack ( - )  :  </td><td align="left">$0.00</td></tr>
                  $html .='<tr>
                      <td align="left">Tax (-)  :  </td>
                      <td align="left">$<span id="storeCloseTaxAmount">';
                      $html .=number_format($totalTax,2);
                      $html .='</span></td>
                  </tr>
                  <tr>
                      <td colspan="2"><div style="background:rgba(51,51,51,1); display:block; height:1px;"></div> </td>
                  </tr>
                </tbody>
                <tbody>
                  <tr>
                      <td align="left"><b>Net Total :</b>  </td>
                      <td align="left">$<span id="currectStoreTotal">';
                      $html .=number_format($closing_amount,2); 
                      $html .='</span></td>
                  </tr>
              </tbody>
            </table>';

            $html .='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td colspan="8"><br><br><br><br></td>
                                        </tr>
                                        <tr>
                                            <td></td>
                                            <td align="center"><hr></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td align="center"><hr></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td></td>
                                            <td align="center">Cashier Sign</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td align="center">Manager Sign</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="8"><hr></td>
                                        </tr>
                                        <tr>
                                            <td colspan="8" align="justify">'.$invInfo->terms.'</td>
                                        </tr>
                                    </tbody>
                                </table>';

            //echo $html; die();

            $mpdf=new Mpdf;
            $mpdf->SetTitle('Store Closing Detail-'.$closing_id);
            $stylesheet=file_get_contents(url('assets/css/bootstrap.min.css'));
            $stylesheet2=file_get_contents(url('assets/css/style.css'));
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
            $mpdf->WriteHTML($html, 2);
            $mpdf->Output('store_closing_detail' . time() . '.pdf', 'I');
            exit();


    }

    public function closeStoreDetail(Request $request)
    {
        $closing_id=$request->storecloseid;
        $closeStoreData=CloseDrawer::find($closing_id);

        $getStoreDateTime=$closeStoreData->opeing_time;
        $getStoreCloseDateTime=$closeStoreData->closing_time;
        $opening_amount=$closeStoreData->opening_amount;

        $totalSales=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('total_amount');

        $totalTax=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('total_tax');

        $totalSalesTender=Invoice::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->select('tender_id','tender_name',\DB::Raw('SUM(total_amount) as tender_total'))
                        ->groupBy('tender_id')
                        ->orderBy('tender_name','ASC')
                        ->get();

        $totalPayoutPlus=Payout::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('amount');

        $totalPayoutMin=Payout::where('store_id',$this->sdc->storeID())
                        ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                        ->sum('negative_amount');

        $totalPayout=$totalPayoutPlus-$totalPayoutMin;
        $array=array('status'=>1,'opening_amount'=>$opening_amount,'opening_time'=>date('d/m/Y',strtotime($getStoreDateTime)),'salesTotal'=>$totalSales,'totalSalesTender'=>$totalSalesTender,'totalTax'=>$totalTax);

        $closing_amount=$totalSales+$opening_amount+$totalPayout;

        $userFullName=$closeStoreData->cashier_name;

        



        $arrayJson=array(
            "OpenDate"=>formatDateTime($getStoreDateTime),
            "CloseDate"=>formatDateTime($getStoreCloseDateTime),
            "totalCollection"=>number_format($totalSales,2),
            "tnderData"=>$totalSalesTender,
            "openingAmount"=>number_format($opening_amount,2),
            "payout"=>number_format($totalPayout,2),
            "tax"=>number_format($totalTax,2),
            "netTotal"=>number_format($closing_amount,2),
            "cashier"=>$userFullName
        );

        return response()->json($arrayJson);



    }

    public function transactionStore()
    {
        $tabCount=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->count();
        if($tabCount>0)
        {
            $storeInfo=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->orderBy('id','DESC')
                            ->first();

            $getStoreDateTime=$storeInfo->opening_time;
            if(empty($storeInfo->opening_time))
            {
                $getStoreDateTime=$storeInfo->created_at;
            }
            $opening_amount=$storeInfo->opening_amount;
            $getStoreCloseDateTime=date('Y-m-d H:i:s');

            $totalSales=InvoicePayment::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('paid_amount');

            $totalTax=Invoice::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('total_tax');

            $totalSalesTender=InvoicePayment::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->select('tender_id','tender_name',\DB::Raw('SUM(paid_amount) as tender_total'))
                            ->groupBy('tender_id')
                            ->orderBy('tender_name','ASC')
                            ->get();

            $totalPayoutPlus=Payout::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('amount');

            $totalPayoutMin=Payout::where('store_id',$this->sdc->storeID())
                            ->whereRaw("created_at >= CAST('".$getStoreDateTime."' as datetime) AND  created_at <=CAST('".$getStoreCloseDateTime."' as datetime)")
                            ->sum('negative_amount');

            //dd($totalPayoutMin);

            $totalPayout=$totalPayoutPlus-$totalPayoutMin;



            $array=array('status'=>1,'opening_amount'=>$opening_amount,'opening_time'=>date('d/m/Y',strtotime($getStoreDateTime)),'salesTotal'=>$totalSales,'totalSalesTender'=>$totalSalesTender,'totalTax'=>$totalTax,'totalPayout'=>$totalPayout);
        }
        else
        {
            $array=array('status'=>0);
        }

        return response()->json($array);
    }


    //paypal intregation end
    public function GenaratePageDataLimit($limit='')
    {
        if(!session::has('pagination_limit') && empty($limit))
        {
            $defaultLimit=16;
            $exSettingsCount=PosSetting::count();
            if($exSettingsCount>0)
            {
                $exSettings=PosSetting::find(1);
                $defaultLimit=$exSettings->pos_item;
            }

            session::put('pagination_limit',$defaultLimit);
        }
        elseif(!session::has('pagination_limit') && !empty($limit))
        {
            session::put('pagination_limit',$limit);
        }
        elseif(session::has('pagination_limit') && !empty($limit))
        {
            session::put('pagination_limit',$limit);
        }

        $newLimit=session::get('pagination_limit')?session::get('pagination_limit'):$limit;

        return $newLimit;
    }

    public function GenaratePageDataFilter($filter='')
    {
        if(!session::has('filter') && empty($filter))
        {
            session::put('filter','id-desc');
        }
        elseif(!session::has('filter') && !empty($filter))
        {
            session::put('filter',$filter);
        }
        elseif(session::has('filter') && !empty($filter))
        {
            session::put('filter',$filter);
        }

        $filterData=session::get('filter')?session::get('filter'):'id-desc';
       

        return $filterData;

    }

    

    public function index()
    {
        $tab_product=Product::where('store_id',$this->sdc->storeID())->get();
        return view('apps.pages.sales.add-sales',['dataProduct'=>$tab_product]);
    }

    private function convertDateFromCard($dateStr='')
    {
        if(!empty($dateStr))
        {
            $dataSplit=explode("/", $dateStr);
            if(count($dataSplit)==2)
            {
                return "20".trim($dataSplit[1])."-".trim($dataSplit[0]);
            }
            
        }
    }

    public function refund(Request $request)
    {
        $id=$request->rid;
        if(!empty($request->rid))
        {
            $refId='ref' .time();
            $aNpH=AuthorizeNetPaymentHistory::find($id);
            //die($aNpH);
            $retData=$this->authorizenet->refundTransaction(
                $aNpH->transactionID,
                $aNpH->card_number,
                $aNpH->card_expire_date,
                $aNpH->paid_amount,
                $aNpH->refTransID);
            if($retData==1)
            {
                $aNpH->refund_status=2;
            }
            else
            {
                $aNpH->refund_status=1;
            }
            $aNpH->save();
            return $retData;
        }
        else
        {
            return 0;
        }
        
           
    }

    public function voidTransaction(Request $request)
    {
        $id=$request->rid;
        if(!empty($request->rid))
        {
            $refId='ref' .time();
            $aNpH=AuthorizeNetPaymentHistory::find($id);
            //die($aNpH);
            $retData=$this->authorizenet->voidTransactions($refId,$aNpH->transactionID);
            if($retData==1)
            {
                $aNpH->refund_status=2;
            }
            else
            {
                $aNpH->refund_status=1;
            }
            $aNpH->save();
            return $retData;
        }
        else
        {
            return 0;
        }
        
           
    }


    public function stripeCardPayment(Request $request)
    {
        $stripe='';
        $stripe_store_settings=\DB::table('stripe_store_settings')->where('module_status',1)->where('store_id',$this->sdc->storeID())->count();
        if($stripe_store_settings>0)
        {
           $stripe=\DB::table('stripe_store_settings')->where('store_id',$this->sdc->storeID())->first();

            $cart = Session::has('Pos') ? Session::get('Pos') : null;
            $invoice_id=$cart->invoiceID;
            $refId=$invoice_id;

            //dd($request);


            $customerInfo=Customer::find($cart->customerID);
            $customerName=$customerInfo->name;

            //dd($customerInfo);

            $totalPrice=$cart->totalPrice;
            $totalTax=$cart->totalTax;
            $discountTotal=$cart->discountTotal;

            $totalInvoicePayable=($totalPrice+$totalTax)-$discountTotal;

            //dd($totalInvoicePayable);
            $posData=serialize(json_encode($cart));

            $checkEx=SessionInvoice::where('store_id',$this->sdc->storeID())->where('invoice_id',$cart->invoiceID)->count();

            if($checkEx==0)
            {
                $sessionInvoice=new SessionInvoice();
                $sessionInvoice->invoice_id=$cart->invoiceID;
                $sessionInvoice->session_pos_data=$posData;
                $sessionInvoice->store_id=$this->sdc->storeID();
                $sessionInvoice->created_by=$this->sdc->UserID();
                $sessionInvoice->save();
            }
            else
            {
                $sessionInvoice=SessionInvoice::where('store_id',$this->sdc->storeID())->where('invoice_id',$cart->invoiceID)->first();
                $sessionInvoice->session_pos_data=$posData;
                $sessionInvoice->updated_by=$this->sdc->UserID();
                $sessionInvoice->save();
            }


            

            if(empty($totalInvoicePayable))
            {
                return redirect(url('pos'))->with('error', 'Your Cart Amount is Empty.');
            }

            //dd($request->amountToPay);

            Stripe\Stripe::setApiKey($stripe->secret_key);
            $payment=Stripe\Charge::create ([
                "amount" => $totalInvoicePayable * 100,
                "currency" => "usd",
                "source" => $request->stripeToken,
                "description" => "INV - ".$invoice_id." payment from v4.nucleuspos.com." 
            ]);

            $paidAmount=$payment->amount/100;

           //dd($payment);              
           

            if($payment->status=="succeeded")
            {

                /*$cardInfoData=new CardInfo;
                $cardInfoData->card_info=$retData['CardType'];
                $cardInfoData->card_number=$cardNumber;
                $cardInfoData->card_name=$request->cardHName;
                $cardInfoData->expriy_date=$expireDate;
                $cardInfoData->pin_number=$request->cardcvc;
                $cardInfoData->store_id=$this->sdc->storeID();
                $cardInfoData->created_by=$this->sdc->UserID();
                $cardInfoData->save();*/

                $tab=new StripeTransactionHistory;
                $tab->invoice_id=$invoice_id;
                $tab->customer_id=$cart->customerID;
                $tab->customer_name=$customerName;
                $tab->transactionID=$payment->id;
                
                $tab->paid_amount=$paidAmount;
                $tab->card_number=$payment->source->last4;
                $tab->card_holder_name="";
                $tab->card_expire_month=$payment->source->exp_month;
                $tab->card_expire_year=$payment->source->exp_year;
                $tab->card_cvc=$payment->source->last4;

//brand
                $tab->authCode=$payment->payment_method;
                $tab->refTransID=$payment->refunds->url;
                $tab->CardType=$payment->source->brand;
                $tab->transactionHash=$payment->balance_transaction;
                $tab->message=json_encode($payment);

                $tab->store_id=$this->sdc->storeID();
                $tab->created_by=$this->sdc->UserID();
                $tab->save();




                $tenderData=Tender::where('stripe',1)->first();
                $payment_method=$tenderData->id;

                $Ncart = new Pos($cart);
                $Ncart->addPayment($paidAmount,$payment_method);
                $request->session()->put('Pos', $Ncart);
                //$cart =$request->session()->has('Pos') ? $request->session()->get('Pos') : null;


                return redirect(url('pos'))->with('status','Payment is successful');
            }
            else
            {
                return redirect(url('pos'))->with('error','Failed, Invalid Card / Insufficient Fund.');
            }


           


        }
        else
        {
            return redirect(url('pos'))->with('error', 'Please Setup Stripe Credential on settings.');
        }



        
        
    }

    public function AuthorizenetCardPayment(Request $request)
    {
        $cart = Session::has('Pos') ? Session::get('Pos') : null;
        $invoice_id=$cart->invoiceID;
        $refId=$invoice_id;
        $cardNumber=trim(str_replace(" ","",$request->cardNumber)); 
        $expireDateStr=trim($request->cardExpire);
        $expireDate=$this->convertDateFromCard($expireDateStr);


        $posData=serialize(json_encode($cart));

        $checkEx=SessionInvoice::where('store_id',$this->sdc->storeID())->where('invoice_id',$cart->invoiceID)->count();

        if($checkEx==0)
        {
            $sessionInvoice=new SessionInvoice();
            $sessionInvoice->invoice_id=$cart->invoiceID;
            $sessionInvoice->session_pos_data=$posData;
            $sessionInvoice->store_id=$this->sdc->storeID();
            $sessionInvoice->created_by=$this->sdc->UserID();
            $sessionInvoice->save();
        }
        else
        {
            $sessionInvoice=SessionInvoice::where('store_id',$this->sdc->storeID())->where('invoice_id',$cart->invoiceID)->first();
            $sessionInvoice->session_pos_data=$posData;
            $sessionInvoice->updated_by=$this->sdc->UserID();
            $sessionInvoice->save();
        }


        if(!$expireDate)
        {
           return response()->json(['status'=>0,'message'=>'Card Expire date invalid.']);
        }

        if(empty($request->amountToPay))
        {
            return response()->json(['status'=>0,'message'=>'Pay amount should not be empty.']);
        }

        //dd($request->amountToPay);

        $retData=$this->authorizenet->captureCardPayment($refId,$cardNumber,$expireDate,$request->amountToPay);

        if($retData['status']==1)
        {

            /*$cardInfoData=new CardInfo;
            $cardInfoData->card_info=$retData['CardType'];
            $cardInfoData->card_number=$cardNumber;
            $cardInfoData->card_name=$request->cardHName;
            $cardInfoData->expriy_date=$expireDate;
            $cardInfoData->pin_number=$request->cardcvc;
            $cardInfoData->store_id=$this->sdc->storeID();
            $cardInfoData->created_by=$this->sdc->UserID();
            $cardInfoData->save();*/

            $tab=new AuthorizeNetPaymentHistory;
            $tab->invoice_id=$refId;
            $tab->card_number=$cardNumber;
            $tab->card_holder_name=$request->cardHName;
            $tab->card_expire_date=$expireDate;
            $tab->card_cvc=$request->cardcvc;
            $tab->paid_amount=$request->amountToPay;

            $tab->refTransID=$retData['refTransID'];

            $tab->authCode=$retData['authCode'];
            $tab->transactionID=$retData['transactionID'];
            $tab->CardType=$retData['CardType'];
            $tab->transactionHash=$retData['transactionHash'];
            $tab->message=$retData['message'];

            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
        }
        
        return response()->json($retData);
    }

    public function AuthorizenetCardPartialPayment(Request $request)
    {

        $invoice_id=$request->invoice_id;
        $refId=$invoice_id;
        $cardNumber=trim(str_replace(" ","",$request->cardNumber)); 
        $expireDateStr=trim($request->cardExpire);
        $expireDate=$this->convertDateFromCard($expireDateStr);

        if(!$expireDate)
        {
           return response()->json(['status'=>0,'message'=>'Card Expire date invalid.']);
        }

        if(empty($request->amountToPay))
        {
            return response()->json(['status'=>0,'message'=>'Pay amount should not be empty.']);
        }

        //dd($request->amountToPay);

        $retData=$this->authorizenet->captureCardPayment($refId,$cardNumber,$expireDate,$request->amountToPay);
        //dd($retData);
        if($retData['status']==1)
        {

            /*$cardInfoData=new CardInfo;
            $cardInfoData->card_info=$retData['CardType'];
            $cardInfoData->card_number=$cardNumber;
            $cardInfoData->card_name=$request->cardHName;
            $cardInfoData->expriy_date=$expireDate;
            $cardInfoData->pin_number=$request->cardcvc;
            $cardInfoData->store_id=$this->sdc->storeID();
            $cardInfoData->created_by=$this->sdc->UserID();
            $cardInfoData->save();*/

            $tab=new AuthorizeNetPaymentHistory;
            $tab->invoice_id=$refId;
            $tab->card_number=$cardNumber;
            $tab->card_holder_name=$request->cardHName;
            $tab->card_expire_date=$expireDate;
            $tab->card_cvc=$request->cardcvc;
            $tab->paid_amount=$request->amountToPay;

            $tab->refTransID=$retData['refTransID'];

            $tab->authCode=$retData['authCode'];
            $tab->transactionID=$retData['transactionID'];
            $tab->CardType=$retData['CardType'];
            $tab->transactionHash=$retData['transactionHash'];
            $tab->message=$retData['message'];

            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();



            $amountPaid=$request->amountToPay;
            //dd($amountPaid);

            $tenderData=Tender::where('name','Card Payment')->first();

            $loadInvoices=Invoice::join('customers','invoices.customer_id','=','customers.id')
                                  ->select(
                                    'invoices.id',
                                    'invoices.invoice_id',
                                    'invoices.total_amount',
                                    'invoices.paid_amount',
                                    'customers.name as customer_name',
                                    \DB::Raw('CASE WHEN lsp_invoices.paid_amount = 0 THEN 
                                        (SELECT SUM(paid_amount) FROM lsp_invoice_payments WHERE lsp_invoice_payments.invoice_id=lsp_invoices.invoice_id)
                                    ELSE lsp_invoices.paid_amount END AS absPaid'),
                                    'invoices.created_at')
                                  ->where('invoices.store_id',$this->sdc->storeID())
                                  ->where('invoices.invoice_id',$invoice_id)
                                  ->whereRaw("lsp_invoices.invoice_status!='Paid'")
                                  ->first();

            $invoice=Invoice::where('invoice_id',$invoice_id)->first();
            //dd($invoiceData);



            $cusInfo=Customer::find($invoice->customer_id);

            $paid_amount=$request->amountToPay;

            $load_total_amount=$loadInvoices->total_amount;
            $load_absPaid=$loadInvoices->absPaid+$paid_amount;
            $load_due=$load_total_amount-$load_absPaid;
            if($load_due>0)
            {
                $load_invoice_status="Partial";
            }
            elseif($load_due<=0)
            {
                $load_invoice_status="Paid";
                $load_due="0.00";
            }
            else
            {
                $load_invoice_status="Partial";
            }

            if(empty($invoice->tender_id))
            {
                $tender=Tender::find($request->payment_method_id);
                $tender_name=$tender->name;
                $tender_id=$tender->id;

                $invoice->tender_id=$tender_id;
                $invoice->tender_name=$tender_name;
                $invoice->save();
            }
            else
            {
                $tender_id=$invoice->tender_id;
                $tender=Tender::find($tender_id);
                $tender_name=$tender->name;
                $invoice->save();
            }

            $chkTicketInvoice=\DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->count();
            if($chkTicketInvoice>0)
            {
                \DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }

            $chkRepairInvoice=\DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->count();
            if($chkRepairInvoice>0)
            {
                \DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }

            $invoicePay=new InvoicePayment;
            $invoicePay->invoice_id=$invoice_id;
            $invoicePay->customer_id=$invoice->customer_id;
            $invoicePay->customer_name=$cusInfo->name;
            $invoicePay->tender_id=$tenderData->id;
            $invoicePay->tender_name=$tenderData->name;
            $invoicePay->total_amount=$invoice->total_amount;
            $invoicePay->paid_amount=$amountPaid;
            $invoicePay->store_id=$this->sdc->storeID();
            $invoicePay->created_by=$this->sdc->UserID();
            $invoicePay->save();

            if($load_due<=0)
            {
                $invoice->due_amount="0.00";
            }
            
            $invoice->invoice_status=$load_invoice_status;
            $invoice->save();





        }
        
        return response()->json($retData);
    }

    public function AuthorizenetCardPaymentPublic(Request $request)
    {

        $invoice_id=$request->invoiceID;
        $refId=$invoice_id;
        $cardNumber=trim(str_replace(" ","",$request->cardNumber)); 
        $expireDateStr=trim($request->cardExpire);
        $expireDate=$this->convertDateFromCard($expireDateStr);

        if(!$expireDate)
        {
           return response()->json(['status'=>0,'message'=>'Card Expire date invalid.']);
        }

        if(empty($request->amountToPay))
        {
            return response()->json(['status'=>0,'message'=>'Pay amount should not be empty.']);
        }

        //dd($request->amountToPay);

        $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();

        $retData=$this->authorizenet->captureCardPayment($refId,$cardNumber,$expireDate,$request->amountToPay,$invoice_id);

        if($retData['status']==1)
        {

            /*$cardInfoData=new CardInfo;
            $cardInfoData->card_info=$retData['CardType'];
            $cardInfoData->card_number=$cardNumber;
            $cardInfoData->card_name=$request->cardHName;
            $cardInfoData->expriy_date=$expireDate;
            $cardInfoData->pin_number=$request->cardcvc;
            $cardInfoData->store_id=$invoiceData->store_id;
            $cardInfoData->created_by=$invoiceData->created_by;
            $cardInfoData->save();*/

            $tab=new AuthorizeNetPaymentHistory;
            $tab->invoice_id=$refId;
            $tab->card_number=$cardNumber;
            $tab->card_holder_name=$request->cardHName;
            $tab->card_expire_date=$expireDate;
            $tab->card_cvc=$request->cardcvc;
            $tab->paid_amount=$request->amountToPay;

            $tab->refTransID=$retData['refTransID'];

            $tab->authCode=$retData['authCode'];
            $tab->transactionID=$retData['transactionID'];
            $tab->CardType=$retData['CardType'];
            $tab->transactionHash=$retData['transactionHash'];
            $tab->message=$retData['message'];

            $tab->store_id=$invoiceData->store_id;
            $tab->created_by=$invoiceData->created_by;
            $tab->save();
        }
        
        return response()->json($retData);
    }

     public function getSalesCartTokenID()
    {

        $session_id=Session::getId(); 
        $counterDisplayIDCheck=CounterDisplay::where('user_id',\Auth::user()->id)->count();
        if($counterDisplayIDCheck>0)
        {
            $tab=CounterDisplay::where('user_id',\Auth::user()->id)->first();
            $tab->session_id=$session_id;
            $tab->user_id=\Auth::user()->id;
            $tab->save();
        }
        else
        {
            $tab=new CounterDisplay;
            $tab->session_id=$session_id;
            $tab->counter_status=0;
            $tab->user_id=\Auth::user()->id;
            $tab->save();
        }

        return response()->json($session_id);
    }  

    public function pos(Request $request)
    {
        
        \DB::statement("call defaultTicketNRepairCreate('".$this->sdc->UserID()."','".$this->sdc->storeID()."')");

        $this->getSalesCartTokenID();
        $filter=$this->GenaratePageDataFilter();
        $tab_customer=Customer::select('id','name')->where('store_id',$this->sdc->storeID())->get();
        $Cart = $request->session()->has('Pos') ? $request->session()->get('Pos') : null;
        if(isset($Cart))
        {
            if(empty($Cart->invoiceID))
            {
                $Ncart = new Pos($Cart);
                $Ncart->genarateInvoiceID();
                $request->session()->put('Pos', $Ncart);
                $Cart =$request->session()->has('Pos') ? $request->session()->get('Pos') : null;
            }
        }
        else
        {
            $Ncart = new Pos($Cart);
            $Ncart->genarateInvoiceID();
            Session::put('Pos', $Ncart);
            $Cart = $request->session()->has('Pos') ? $request->session()->get('Pos') : null;
        }

        if(Invoice::count()>0)
        {
            $sql_invoice_id=Invoice::select("id")->orderBy("id","DESC")->first();
            $last_invoice_id=$sql_invoice_id->id;
        }
        else
        {
            $last_invoice_id=0;
        }



        $ps=PosSetting::where('store_id',$this->sdc->storeID())->first();
        $pro=Product::where('store_id',$this->sdc->storeID())->where('general_sale',0)->get();
        //dd($pro);
        /*->when($filter, function($query) use ($filter){
            if($filter=='id-desc'){ return $query->orderby('id','desc'); }
            elseif($filter=='price:asc'){ return $query->orderby('price','asc'); }
            elseif($filter=='price:desc'){ return $query->orderby('price','desc'); }
            elseif($filter=='name:asc'){ return $query->orderby('name','asc'); }
            elseif($filter=='name:desc'){ return $query->orderby('name','desc'); }
            elseif($filter=='quantity:desc'){ return $query->orderby('quantity','desc'); }
            elseif($filter=='position:asc'){ return $query->orderby('id','desc'); }
            else{ return $query->orderby('id','desc'); }                    
        })
        ->paginate($this->GenaratePageDataLimit());*/

        $CounterDisplay=$this->counterDisplayCheck();

        $tender=Tender::where('store_id',$this->sdc->storeID())->get();
        $authorizeNettender=Tender::where('authorizenet',1)->get();
        $payPaltender=Tender::where('paypal',1)->get();

        $drawerStatus=OpenDrawer::where('store_id',$this->sdc->storeID())
                            ->where('store_status','Open')
                            ->count();

        $catInfo=Category::where('store_id',$this->sdc->storeID())->get(); 

        $device=InStoreRepairDevice::select('id','name')->where('store_id',$this->sdc->storeID())->get();
        $model=InStoreRepairModel::select('id','name','device_id')->where('store_id',$this->sdc->storeID())->get();
        $problem=InStoreRepairProblem::select('id','name','model_id')->where('store_id',$this->sdc->storeID())->get();
        $estPrice=InStoreRepairPrice::select('id','price','device_id','model_id','problem_id','device_name','model_name','problem_name')->where('store_id',$this->sdc->storeID())->get();
        $ticketAsset=\DB::table('repair_ticket_assets')->where('asset_type','ticket')->where('store_id',$this->sdc->storeID())->get();
        $repairAsset=\DB::table('repair_ticket_assets')->where('asset_type','repair')->where('store_id',$this->sdc->storeID())->get();

        $ticketProblem=TicketProblem::select('id','name')->where('store_id',$this->sdc->storeID())->get();

        $addPartialPayment=0;
        if(Session::has('addPartialPayment'))
        {
            if(Session::get('addPartialPayment')==1)
            {
                $addPartialPayment=1;
                Session::put('addPartialPayment',0);
            }
        }

        $partial_invoice=0;
        if(Session::has('partial_invoice'))
        {
            if(Session::get('partial_invoice')>0)
            {
                $partial_invoice=Session::get('partial_invoice');
                Session::put('partial_invoice',0);
            }
        }


        $stripe='';
        $stripe_store_settings=\DB::table('stripe_store_settings')->where('module_status',1)->where('store_id',$this->sdc->storeID())->count();
        if($stripe_store_settings>0)
        {
           $stripe=\DB::table('stripe_store_settings')->where('store_id',$this->sdc->storeID())->first();
        }
        

        return view('apps.pages.pos.index',
            [
                'product'=>$pro,
                'tender'=>$tender,
                'catInfo'=>$catInfo,
                'payPaltender'=>$payPaltender,
                'drawerStatus'=>$drawerStatus,
                'authorizeNettender'=>$authorizeNettender,
                'ps'=>$ps,
                'cart'=>$Cart,
                'customerData'=>$tab_customer,
                "last_invoice_id"=>$last_invoice_id,
                'CounterDisplay'=>$CounterDisplay,
                'device'=>$device,
                'model'=>$model,
                'problem'=>$problem,
                'addPartialPayment'=>$addPartialPayment,
                'partial_invoice'=>$partial_invoice,
                'ticketProblem'=>$ticketProblem,
                'estPrice'=>$estPrice,
                'repairAsset'=>$repairAsset,
                'ticketAsset'=>$ticketAsset,
                'ticket_id'=>time(),
                'stripe'=>$stripe
            ]);
    }

    public function GenaratePDF()
    {
        $mpdf = new Mpdf;
        $mpdf->WriteHTML('<h1>Hello world!</h1>');
        $mpdf->Output();
    }

    private function counterDisplayCheck()
    {
        $session_id=Session::getId(); 
        $counterStatus=0;

        $counterDisplayIDCheck=CounterDisplay::where('user_id',\Auth::user()->id)->count();
        if($counterDisplayIDCheck==0)
        {
            $tab=new CounterDisplay;
            $tab->session_id=$session_id;
            $tab->counter_status=$counterStatus;
            $tab->user_id=\Auth::user()->id;
            $tab->save();
        }
        else
        {
            $tab=CounterDisplay::where('user_id',\Auth::user()->id)->first();
            $counterStatus=$tab->counter_status;
        }



        return $counterStatus;
    }

    
    public function invoicePDF(Invoice $invoice,$invoice_id=0)
    {

        if(!empty($invoice_id))
        {

            $tab_invoice=$invoice::join('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          'tenders.name as tender',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.id',$invoice_id)
                                 ->where('invoices.store_id',$this->sdc->storeID())
                                 ->first();

            if(!isset($tab_invoice))
            {
                return redirect('pos')->with('error','Create new sales & please configure your invoice settings.'); 
            }
                                 
            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 ->where('store_id',$this->sdc->storeID())
                                 //->groupBy("invoice_id")
                                 ->sum('paid_amount');                     

            $customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               ->where('invoice_products.store_id',$this->sdc->storeID())
                                               ->select(
                                                        'invoice_products.product_id',
                                                        'invoice_products.price',
                                                        'invoice_products.quantity',
                                                        'invoice_products.total_price',
                                                        'products.name as product_name'
                                                        )
                                               ->get();



                $invInfo=$this->sdc->Invlayout();
                $mpdf=new Mpdf;
                $mpdf->SetTitle('INV-'.$tab_invoice->id);


                if(empty($invInfo->company_name))
                {
                    return redirect('pos')->with('error','Please configure your invoice settings.'); 
                }

                if(!file_exists('company/'.$invInfo->logo))
                {
                    return redirect()->back()->with('error', ' Invoice failed to load, Please Set Invoice/Report Logo. !');
                }

                //$mpdf->SetDisplayMode('fullpage');
                //$mpdf->list_indent_first_level=0; // 1 or 0 - whether to indent the first level of a list
                // LOAD a stylesheet
                $stylesheet=file_get_contents(url('assets/css/bootstrap.min.css'));
                $stylesheet2=file_get_contents(url('assets/css/style.css'));
                $html='<div class="container" id="report_container" style="border: 1px #ccc solid;">
                    <table  class="col-md-12" cellpadding="10" style="width:100%;" width="100%;">
                        <tr>
                        <td valign="top" width="200">
                    <div class="col-lg-3">
                        <div class="col-md-12" style="border-bottom: 5px #000 solid; font-size: 20px; font-weight: bold; padding-left: 0px;">
                            ' . formatDateTime($tab_invoice->created_at) . '<hr style="height:5px;">
                        </div>
                        <div class="col-md-12" style="padding-top: 20px; padding-bottom: 4px; color: #000; padding-left: 0px;">
                            <b><br />Customer Info</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $customer->name . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->address . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->email . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->phone . '
                        </div>

                        <div class="col-md-12" style="padding-top: 21px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                            <b><br />Ship To :</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $customer->name . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->address . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->email . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->phone . '
                        </div>


                        <div class="col-md-12" style="padding-top: 35px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                            <b><br />Payment Method :</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $tab_invoice->tender . '
                </div>

                <div class="col-md-12" style="padding-top: 30px; padding-bottom: 4px; padding-left: 0px;">
                <img src="'.url('company/'.$invInfo->logo).'" style="width:100px; margin-top:10px;">
                </div>

                </div>
                </td>
                <td valign="top">
                <div class="col-lg-9" style="float:left; margin-top:-50px;">
                <div class="col-md-12" style="border-bottom: 5px #000 solid; color: #008000; font-size: 20px; font-weight: bold; padding-left: 0px;">
                '.$invInfo->company_name.'<hr style="height:5px;">
                </div>
                <div class="col-md-12" style="padding-top: 10px; padding-bottom: 5px; padding-left: 0px; font-size: 13px;">
                <b>'.$invInfo->company_thank_you_message.'<br /></b>
                </div>
                <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; font-size: 10px; padding-left: 0px;">'.$invInfo->company_services.'
                <br /><br />
                </div>
                <div class="col-md-12" style="padding-top: 4px;  padding-bottom: 4px; padding-left: 0px;">
                <table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >Item Number</th>
                <th class="text-center" style="font-size:12px;" >Description</th>
                <th class="text-center" style="font-size:12px;" >Price</th>
                <th class="text-center" style="font-size:12px;" >Quantity</th>
                <th class="text-center" style="font-size:12px;" >Amount</th>
                </tr>
                </thead>
                <tbody>';

                $subTotal=0;
                $total=0;
                $tender='';
                $ai=0;
                $ai_quantity=0;
                if(isset($tab_invoice_product)){
                    foreach($tab_invoice_product as $inv){
                        $html .='<tr>
                                    <td style="font-size:12px;" class="text-center">' . $inv->product_id . '</td>
                                    <td style="font-size:12px; width:200px;">' . $inv->product_name . '</td>
                                    <td style="font-size:12px;" class="text-center">' . $inv->price . '</td>
                                    <td style="font-size:12px;" class="text-center">' . $inv->quantity . '</td>
                                    <td style="font-size:12px;" class="text-right">' . number_format($inv->total_price, 2) . '</td>
                                </tr>';

                        $ai_quantity+=$inv->quantity;
                        $ai+=1;
                    }
                }

                for ($i=1; $i <= 13 - $ai; $i++):
                    $html .='<tr>
                <td>&nbsp;
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                </tr>';
                endfor;

                $RowsubTotal=($tab_invoice->total_amount+$tab_invoice->sales_discount)-$tab_invoice->total_tax;

                $html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : ' . $ai_quantity . '</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">' . number_format($RowsubTotal, 2) . '</td>
                </tr>

                </tbody>
                <tfoot>
                <tr>
                <td colspan="2" rowspan="5" style="font-size:10px;"> 
                Sales Invoice ID: '.$tab_invoice->invoice_id.' <br><br> 
                Sales Tax Rate: ' . number_format($tab_invoice->tax_rate, 2) . '% <br><br>';

                if($tab_invoice->discount_type==1)
                {
                    $html .='Discount Amount is '.number_format($tab_invoice->discount_total, 2);
                }
                elseif($tab_invoice->discount_type==2)
                {
                    $html .='Discount Rate : '.number_format($tab_invoice->sales_discount, 2).'%';
                }

                $html .='</td>
                <td colspan="2" style="font-size:10px;" class="text-right">Sales Tax (+)</td>
                <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->total_tax, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Discount (-)</td>
                <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->discount_total, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Invoice Total</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($tab_invoice->total_amount, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Paid Amount</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($invoice_payment, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Invoice Due</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;"><b>'; 
                $invoiceDue=$tab_invoice->total_amount-$invoice_payment;
                if($invoiceDue>0)
                {
                    $html .=number_format($invoiceDue, 2);
                }
                else
                {
                    $html .="0.00";
                }
           
                $html .='</b></td>
                </tr>
                </tfoot>


                </table>
                </div>
                </div>
                </td>
                <tr>
                </table>
                <div class="row">
                <div class="col-lg-12" style="padding-left: 5px; padding-top: 1px; margin-top:-20px;">
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_one.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_two.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_three.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_four.'</b>
                </div>
                <br />
                <table width="100%" style="margin-left:25px;" class="col-md-12">
                <tr>
                <td>
                <div class="col-md-11 text-left" style="padding-left: 0px; border-bottom: 3px #000 solid; font-size: 28px; color: #008000;">
                <b>
                '.$invInfo->fotter_company_name.'</b><hr style="height:5px; margin-top:0px;">
                </div>
                </td>
                <td width="50">
                <img width="60" src="'.url('company/'.$invInfo->logo_fotter).'">
                </td>
                </tr>
                </table>


                <div class="col-md-12" style="clear: both;">
                <br />
                <table width="100%" class="col-md-12">
                <tr>
                <td style="text-align:center; font-size:10px;">
                '.$invInfo->c_one.'
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-5 text-center">
                '.$invInfo->c_two.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-4 text-center">
                '.$invInfo->c_three.'
                </div>
                </td>
                </tr>
                <tr>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-3 text-center">
                '.$invInfo->c_four.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-5 text-center">
                '.$invInfo->c_five.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-4 text-center">
                '.$invInfo->c_six.'
                </div>
                </td>
                </tr>
                </tr>
                </table>
                </div>
                <div class="col-md-12" style="border-bottom: 5px #000 solid; margin-left:15px; clear: both;">
                <hr style="height:5px; margin-top:5px;">
                <br>
                '.$invInfo->terms.'
                </div>
                </div>
                </div>
                </div>';
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
                $mpdf->WriteHTML($html, 2);
                $mpdf->Output('invoice_' . time() . '.pdf', 'I');
                exit();



            
        }
        else
        {
            return redirect('sales/report')->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }



    }

    public function captureInvoicePDF(Invoice $invoice,$invoice_id=0)
    {

        if(!empty($invoice_id))
        {

            $tab_invoice=$invoice::join('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          'invoices.store_id',
                                          'tenders.name as tender',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.id',$invoice_id)
                                 ->first();
                                 
            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                        ->sum('paid_amount');                     

            $customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               //->where('invoice_products.store_id',$this->sdc->storeID())
                                               ->select('invoice_products.*','products.name as product_name')
                                               ->get();



                $invInfo=$this->sdc->Invlayout($tab_invoice->store_id);
                if(!file_exists('company/'.$invInfo->logo))
                {
                    return redirect()->back()->with('error', ' Invoice failed to load, Please Set Invoice/Report Logo. !');
                }
                $mpdf=new Mpdf;
                $mpdf->SetTitle('INV-'.$tab_invoice->id);
                //$mpdf->SetDisplayMode('fullpage');
                //$mpdf->list_indent_first_level=0; // 1 or 0 - whether to indent the first level of a list
                // LOAD a stylesheet
                $stylesheet=file_get_contents(url('assets/css/bootstrap.min.css'));
                $stylesheet2=file_get_contents(url('assets/css/style.css'));
                $html='<div class="container" id="report_container" style="border: 1px #ccc solid;">
                    <table  class="col-md-12" cellpadding="10" style="width:100%;" width="100%;">
                        <tr>
                        <td valign="top" width="200">
                    <div class="col-lg-3">
                        <div class="col-md-12" style="border-bottom: 5px #000 solid; font-size: 20px; font-weight: bold; padding-left: 0px;">
                            ' . formatDateTime($tab_invoice->created_at) . '<hr style="height:5px;">
                        </div>
                        <div class="col-md-12" style="padding-top: 20px; padding-bottom: 4px; color: #000; padding-left: 0px;">
                            <b><br />Customer Info</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $customer->name . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->address . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->email . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->phone . '
                        </div>

                        <div class="col-md-12" style="padding-top: 21px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                            <b><br />Ship To :</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $customer->name . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->address . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->email . '
                        </div>
                        <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                            ' . $customer->phone . '
                        </div>


                        <div class="col-md-12" style="padding-top: 35px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                            <b><br />Payment Method :</b>
                        </div>
                        <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                            ' . $tab_invoice->tender . '
                </div>

                <div class="col-md-12" style="padding-top: 30px; padding-bottom: 4px; padding-left: 0px;">
                <img src="'.url('company/'.$invInfo->logo).'" style="width:100px; margin-top:10px;">
                </div>

                </div>
                </td>
                <td valign="top">
                <div class="col-lg-9" style="float:left; margin-top:-50px;">
                <div class="col-md-12" style="border-bottom: 5px #000 solid; color: #008000; font-size: 20px; font-weight: bold; padding-left: 0px;">
                '.$invInfo->company_name.'<hr style="height:5px;">
                </div>
                <div class="col-md-12" style="padding-top: 10px; padding-bottom: 5px; padding-left: 0px; font-size: 13px;">
                <b>'.$invInfo->company_thank_you_message.'<br /></b>
                </div>
                <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; font-size: 10px; padding-left: 0px;">'.$invInfo->company_services.'
                <br /><br />
                </div>
                <div class="col-md-12" style="padding-top: 4px;  padding-bottom: 4px; padding-left: 0px;">
                <table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >Item Number</th>
                <th class="text-center" style="font-size:12px;" >Description</th>
                <th class="text-center" style="font-size:12px;" >Price</th>
                <th class="text-center" style="font-size:12px;" >Quantity</th>
                <th class="text-center" style="font-size:12px;" >Amount</th>
                </tr>
                </thead>
                <tbody>';

                $subTotal=0;
                $total=0;
                $tender='';
                $ai=0;
                $ai_quantity=0;
                if(isset($tab_invoice_product)){
                    foreach($tab_invoice_product as $inv){
                        $html .='<tr>
                    <td style="font-size:12px;" class="text-center">' . $inv->product_id . '</td>
                    <td style="font-size:12px; width:200px;">' . $inv->product_name . '</td>
                    <td style="font-size:12px;" class="text-center">' . $inv->price . '</td>
                    <td style="font-size:12px;" class="text-center">' . $inv->quantity . '</td>
                    <td style="font-size:12px;" class="text-right">' . number_format($inv->total_price, 2) . '</td>
                    </tr>';

                        $ai_quantity+=$inv->quantity;
                        $ai+=1;
                    }
                }

                for ($i=1; $i <= 16 - $ai; $i++):
                    $html .='<tr>
                <td>&nbsp;
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                </tr>';
                endfor;
                $html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : ' . $ai_quantity . '</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">' . number_format((($tab_invoice->total_amount+$tab_invoice->sales_discount)-$tab_invoice->total_tax), 2) . '</td>
                </tr>

                </tbody>
                <tfoot>
                <tr>
                <td colspan="2" rowspan="5" style="font-size:10px;"> Sales Tax Rate: ' . number_format($tab_invoice->tax_rate, 2) . '% <br><br>';

                if($tab_invoice->discount_type==1)
                {
                    $html .='Discount Amount is '.number_format($tab_invoice->discount_total, 2);
                }
                elseif($tab_invoice->discount_type==2)
                {
                    $html .='Discount Rate : '.number_format($tab_invoice->sales_discount, 2).'%';
                }

                $html .='</td>
                <td colspan="2" style="font-size:10px;" class="text-right">Sales Tax (+)</td>
                <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->total_tax, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Discount (-)</td>
                <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->discount_total, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Invoice Total</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($tab_invoice->total_amount, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Paid Amount</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($invoice_payment, 2) . '</td>
                </tr>
                <tr>
                <td style="font-size:10px;" colspan="2" class="text-right">Invoice Due</td>
                <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;"><b>'; 

                if(($tab_invoice->total_amount-$invoice_payment)>0)
                {
                    $html .=number_format(($tab_invoice->total_amount-$invoice_payment), 2);
                }
                else
                {
                    $html .="0.00";
                }
           
                $html .='</b></td>
                </tr>
                </tfoot>


                </table>
                </div>
                </div>
                </td>
                <tr>
                </table>
                <div class="row">
                <div class="col-lg-12" style="padding-left: 5px; padding-top: 1px; margin-top:-20px;">
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_one.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_two.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_three.'</b>
                </div>
                <div class="col-md-12 text-center">
                <b>'.$invInfo->mm_four.'</b>
                </div>
                <br />
                <table width="100%" style="margin-left:25px;" class="col-md-12">
                <tr>
                <td>
                <div class="col-md-11 text-left" style="padding-left: 0px; border-bottom: 3px #000 solid; font-size: 28px; color: #008000;">
                <b>
                '.$invInfo->fotter_company_name.'</b><hr style="height:5px; margin-top:0px;">
                </div>
                </td>
                <td width="50">
                <img src="'.url('company/'.$invInfo->logo_fotter).'">
                </td>
                </tr>
                </table>


                <div class="col-md-12" style="clear: both;">
                <br />
                <table width="100%" class="col-md-12">
                <tr>
                <td style="text-align:center; font-size:10px;">
                '.$invInfo->c_one.'
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-5 text-center">
                '.$invInfo->c_two.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-4 text-center">
                '.$invInfo->c_three.'
                </div>
                </td>
                </tr>
                <tr>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-3 text-center">
                '.$invInfo->c_four.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-5 text-center">
                '.$invInfo->c_five.'
                </div>
                </td>
                <td style="text-align:center; font-size:10px;">
                <div class="col-md-4 text-center">
                '.$invInfo->c_six.'
                </div>
                </td>
                </tr>
                </tr>
                </table>
                </div>
                <div class="col-md-12" style="border-bottom: 5px #000 solid; margin-left:15px; clear: both;">
                <hr style="height:5px; margin-top:5px;">
                </div>
                </div>
                </div>
                </div>';
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
                $mpdf->WriteHTML($html, 2);
                $mpdf->Output('invoice_' . time() . '.pdf', 'I');
                exit();



            
        }
        else
        {
            return redirect('sales/report')->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }



    }


    private function InvoicePaymentByInvoice($invoiceID,$storeID=0)
    {
        if(!empty($storeID))
        {
            $dataCount=InvoicePayment::where('invoice_id',$invoiceID)
                                 ->where('store_id',$storeID)
                                 ->count();
            $dataResult="";
            if($dataCount>0)
            {
                $data=InvoicePayment::where('invoice_id',$invoiceID)
                                     ->where('store_id',$storeID)
                                     ->get();

                $dataResult=$data;
                return $dataResult;
            }
            else
            {
                return $dataResult;
            }
        }
        else
        {
            $dataCount=InvoicePayment::where('invoice_id',$invoiceID)
                                 ->where('store_id',$this->sdc->storeID())
                                 ->count();
            $dataResult="";
            if($dataCount>0)
            {
                $data=InvoicePayment::where('invoice_id',$invoiceID)
                                     ->where('store_id',$this->sdc->storeID())
                                     ->get();

                $dataResult=$data;
                return $dataResult;
            }
            else
            {
                return $dataResult;
            }
        }
        
    }

    public function invoicePDFByMedia(Invoice $invoice,$ptype='pos',$invoice_id=0)
    {

        if(!empty($invoice_id))
        {

            $tab_invoice=$invoice::join('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          'invoices.created_by',
                                          'tenders.name as tender',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.id',$invoice_id)
                                 ->where('invoices.store_id',$this->sdc->storeID())
                                 ->first();
                                 
            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 ->where('store_id',$this->sdc->storeID())
                                 //->groupBy("invoice_id")
                                 ->sum('paid_amount');                     

            $customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               ->where('invoice_products.store_id',$this->sdc->storeID())
                                               ->select('invoice_products.*','products.name as product_name')
                                               ->get();

                $invInfo=$this->sdc->Invlayout();
                if(!file_exists('company/'.$invInfo->logo))
                {
                    return redirect()->back()->with('error', ' Invoice failed to load, Please Set Invoice/Report Logo. !');
                }
                //dd($invInfo);

                $dataPageLayout=$this->sdc->DefaultPrinterPrintSize();

                if($ptype=='thermal')
                {
                    $thermalPageWidth=$dataPageLayout->thermal_width;
                    $thermalPageHeight=$dataPageLayout->thermal_height;
                    $address='';
                    if(isset($invInfo->address))
                    {
                        $address=$invInfo->address;
                    }

                    if(isset($invInfo->mm_four))
                    {
                        $address=$invInfo->mm_four;
                    }

                    $userInfo=User::find($tab_invoice->created_by);
                    $userNameLength=strlen($userInfo->name);
                    $userName=$userInfo->name;
                    if($userNameLength>20)
                    {
                        $userName=substr($userInfo->name,0,20)."...";
                    }

                    $thermalHtml='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <img class="logo" height="45" src="'.url('company/'.$invInfo->logo).'" alt="brand logo"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$invInfo->company_name.'</td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$invInfo->c_one.'</td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$address.'</td>
                                        </tr>
                                    </tbody>
                                </table>';

                    $thermalHtml.='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td colspan="4"><hr></td>
                                        </tr>
                                        <tr>
                                            <td><b>Invoice No</b></td>
                                            <td colspan="3"><b>: '.$tab_invoice->invoice_id.'</b></td>
                                        </tr>
                                        <tr>
                                            <td>Cashier</td>
                                            <td colspan="3">: '.$userName.'</td>
                                        </tr>
                                        <tr>
                                            <td>Date Time</td>
                                            <td colspan="3">: '.formatDateTime($tab_invoice->created_at).'</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><hr></td>
                                        </tr>
                                    </tbody>
                                </table>';

                    $thermalHtml.='<table align="center" class="mt-10" width="100%">
                                    <thead class="bt-1 bb-1">
                                        <tr>
                                            <td><b>SL</b></td>
                                            <td align="center"><b>Product</b></td>
                                            <td align="right"><b>Total (TK)</b></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3"><hr></td>
                                        </tr>';
                    $subTotal=0;
                    $total=0;
                    $tender='';
                    $ai=0;
                    $ai_quantity=0;
                    if(isset($tab_invoice_product)){
                        foreach($tab_invoice_product as $inv){

                                $thermalHtml.='     <tr>
                                                        <td>'.($ai+1).'</td>
                                                        <td colspan="2">'.$inv->product_name.'</td>
                                                    </tr>
                                                    <tr>
                                                        <td></td>
                                                        <td align="right">'.$inv->price.' X '.$inv->quantity.'</td>
                                                        <td align="right">'.$inv->total_price.'</td>
                                                    </tr>';
                            $ai_quantity+=$inv->quantity;
                            $subTotal+=$inv->total_price;
                            $ai+=1;
                            $thermalPageHeight+=8;
                        }
                    }

                    


                    $thermalHtml.=' </tbody>
                                </table>

                                <table align="center" class="mt-15" width="100%">
                                    <thead class="bt-1 bb-1">
                                        <tr>
                                            <th colspan="4" align="left" style="text-align:center;">
                                             <hr>
                                             Bill Summary
                                             <hr>  
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td width="33%" colspan="2" align="right"><b>Gross Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($subTotal, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>VAT Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($tab_invoice->total_tax, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>Net Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format(($subTotal+$tab_invoice->total_tax), 2).'</b></td>
                                        </tr>
                                    </tbody>
                                    <tbody class="bt-1 bb-1">
                                        <tr>
                                            <td colspan="4" align="right"><hr></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>*Discount Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($tab_invoice->discount_total, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>Net Payable</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format((($subTotal+$tab_invoice->total_tax)-$tab_invoice->discount_total), 2).'</b></td>
                                        </tr>
                                    </tbody>
                                    <thead class="bt-1 pt-15">
                                        <tr>
                                            <th colspan="4" align="left" style="text-align:center;">
                                             <hr>
                                             Payment Details  
                                             <hr>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bb-1">';
                $dataInvPa=$this->InvoicePaymentByInvoice($tab_invoice->invoice_id);
                if(isset($dataInvPa))
                {
                    foreach($dataInvPa as $vpa):
                        $thermalHtml.='     <tr>
                                                <td colspan="2" width="33%" align="right"><b>'.$vpa->tender_name.'</b></td>
                                                <td width="10%" align="center"><b>:</b></td>
                                                <td align="right"><b>$'.number_format($vpa->paid_amount,2).'</b></td>
                                            </tr>';
                    endforeach;
                }
                

                $thermalHtml.='         <tr>
                                            <td colspan="4" align="right"><hr></td>
                                        </tr>';

                if(!empty($invInfo->terms))
                {

                    $termsLength=intval((strlen($invInfo->terms))/40);

                    $thermalPageHeight+=($termsLength*3);

                    $thermalHtml.='         <tr>
                                                <td colspan="4" align="center"><u><b>Terms &amp; Condition</b></u>
                                                <br>

                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" align="center">
                                                '.$invInfo->terms.'
                                                </td>
                                            </tr>';
                }


                $thankYouMessage='';
                if(isset($invInfo->company_thank_you_message))
                {
                    $thankYouMessage=$invInfo->company_thank_you_message;
                }

                $thermalHtml.='         <tr>
                                            <td colspan="4" align="center"><b>'.$thankYouMessage.'</b></td>
                                        </tr>
                                    </tbody>
                                </table>';


                                

                    
                    $mpdf = new Mpdf([
                                        'mode' => '', 
                                        'format'               =>[$thermalPageWidth,$thermalPageHeight],
                                        'default_font_size'    => '8',
                                        'default_font'         => 'serif',
                                        'margin_left'          => 3,
                                        'margin_right'         => 3,
                                        'margin_top'           => 5,
                                        'margin_bottom'        => 0,
                                        'margin_header'        => 0,
                                        'margin_footer'        => 0,
                                        'orientation'          => 'P',
                                        'title'                => 'Thermal Invoice Printer',
                                        'author'               => '',
                                        'watermark'            => 'SimpleRetailPos',
                                        'show_watermark'       => true,
                                        'watermark_font'       => 'sans-serif',
                                        'display_mode'         => 'fullpage',
                                        'watermark_text_alpha' => 0.1
                                    ]);
                    $mpdf->SetDisplayMode('fullpage');
                    $mpdf->SetTitle('INV-'.$tab_invoice->id);
                    $stylesheet=file_get_contents(url('pdf/thermal.css'));
                    $stylesheet2=file_get_contents(url('assets/css/style.css'));

                    $mpdf->WriteHTML($stylesheet, 1);
                    $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
                    $mpdf->WriteHTML($thermalHtml, 2);
                    $mpdf->Output('invoice_' . time() . '.pdf', 'I');
                    exit();
                  
                }
                elseif($ptype=='barcode')
                {

                    $thermalPageWidth=$dataPageLayout->barcode_width;
                    $thermalPageHeight=$dataPageLayout->barcode_height;
                    $address='';
                    if(isset($invInfo->address))
                    {
                        $address=$invInfo->address;
                    }

                    if(isset($invInfo->mm_four))
                    {
                        $address=$invInfo->mm_four;
                    }

                    $userInfo=User::find($tab_invoice->created_by);
                    $userNameLength=strlen($userInfo->name);
                    $userName=$userInfo->name;
                    if($userNameLength>20)
                    {
                        $userName=substr($userInfo->name,0,20)."...";
                    }

                    $barcode = $this->sdc->GenarateBarcode($tab_invoice->invoice_id);

                    $barcodeImage='<img src="data:image/png;base64,'.$barcode.'" />';

                    $thermalHtml='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <img class="logo" height="45" src="'.url('company/'.$invInfo->logo).'" alt="brand logo"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$invInfo->company_name.'</td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$invInfo->c_one.'</td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$address.'</td>
                                        </tr>
                                        <tr>
                                            <td align="center">'.$barcodeImage.'</td>
                                        </tr>
                                    </tbody>
                                </table>';

                    $thermalHtml.='<table align="center" width="100%">
                                    <tbody>
                                        <tr>
                                            <td><hr></td>
                                        </tr>
                                        <tr>
                                            <td><b>Invoice No : '.$tab_invoice->invoice_id.'</b></td>
                                        </tr>
                                        <tr>
                                            <td>Cashier : '.$userName.'</td>
                                        </tr>
                                        <tr>
                                            <td>Date Time : '.formatDateTime($tab_invoice->created_at).'</td>
                                        </tr>
                                        <tr>
                                            <td><hr></td>
                                        </tr>
                                    </tbody>
                                </table>';

                    $thermalHtml.='<table align="center" class="mt-10" width="100%">
                                    <thead class="bt-1 bb-1">
                                        <tr>
                                            <td><b>SL</b></td>
                                            <td align="center"><b>Product</b></td>
                                            <td align="right"><b>Total (TK)</b></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3"><hr></td>
                                        </tr>';
                    $subTotal=0;
                    $total=0;
                    $tender='';
                    $ai=0;
                    $ai_quantity=0;
                    if(isset($tab_invoice_product)){
                        foreach($tab_invoice_product as $inv){

                                $thermalHtml.='     <tr>
                                                        <td>'.($ai+1).'</td>
                                                        <td colspan="2">'.$inv->product_name.'</td>
                                                    </tr>
                                                    <tr>
                                                        <td></td>
                                                        <td align="right">'.$inv->price.' X '.$inv->quantity.'</td>
                                                        <td align="right">'.$inv->total_price.'</td>
                                                    </tr>';
                            $ai_quantity+=$inv->quantity;
                            $subTotal+=$inv->total_price;
                            $ai+=1;
                            $thermalPageHeight+=12;
                        }
                    }

                    


                    $thermalHtml.=' </tbody>
                                </table>

                                <table align="center" class="mt-15" width="100%">
                                    <thead class="bt-1 bb-1">
                                        <tr>
                                            <th colspan="4" align="left" style="text-align:center;">
                                             <hr>
                                             Bill Summary
                                             <hr>  
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td width="33%" colspan="2" align="right"><b>Gross Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($subTotal, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>VAT Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($tab_invoice->total_tax, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>Net Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format(($subTotal+$tab_invoice->total_tax), 2).'</b></td>
                                        </tr>
                                    </tbody>
                                    <tbody class="bt-1 bb-1">
                                        <tr>
                                            <td colspan="4" align="right"><hr></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>*Discount Amount</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format($tab_invoice->discount_total, 2).'</b></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" width="33%" align="right"><b>Net Payable</b></td>
                                            <td width="10%" align="center"><b>:</b></td>
                                            <td align="right"><b>$'.number_format((($subTotal+$tab_invoice->total_tax)-$tab_invoice->discount_total), 2).'</b></td>
                                        </tr>
                                    </tbody>
                                    <thead class="bt-1 pt-15">
                                        <tr>
                                            <th colspan="4" align="left" style="text-align:center;">
                                             <hr>
                                             Payment Details  
                                             <hr>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bb-1">';
                $dataInvPa=$this->InvoicePaymentByInvoice($tab_invoice->invoice_id);
                if(isset($dataInvPa))
                {
                    foreach($dataInvPa as $vpa):
                        $thermalHtml.='     <tr>
                                                <td colspan="2" width="33%" align="right"><b>'.$vpa->tender_name.'</b></td>
                                                <td width="10%" align="center"><b>:</b></td>
                                                <td align="right"><b>$'.number_format($vpa->paid_amount,2).'</b></td>
                                            </tr>';
                    endforeach;
                }
                

                $thermalHtml.='         <tr>
                                            <td colspan="4" align="right"><hr></td>
                                        </tr>';

                if(!empty($invInfo->terms))
                {

                    $termsLength=intval((strlen($invInfo->terms))/40);

                    $thermalPageHeight+=($termsLength*3);

                    $thermalHtml.='         <tr>
                                                <td colspan="4" align="center"><u><b>Terms &amp; Condition</b></u>
                                                <br>

                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" align="center">
                                                '.$invInfo->terms.'
                                                </td>
                                            </tr>';
                }


                $thankYouMessage='';
                if(isset($invInfo->company_thank_you_message))
                {
                    $thankYouMessage=$invInfo->company_thank_you_message;
                }

                $thermalHtml.='         <tr>
                                            <td colspan="4" align="center"><b>'.$thankYouMessage.'</b></td>
                                        </tr>
                                    </tbody>
                                </table>';


                                

                    
                    $mpdf = new Mpdf([
                                        'mode' => '', 
                                        'format'               =>[$thermalPageWidth,$thermalPageHeight],
                                        'default_font_size'    => '8',
                                        'default_font'         => 'serif',
                                        'margin_left'          => 3,
                                        'margin_right'         => 3,
                                        'margin_top'           => 5,
                                        'margin_bottom'        => 0,
                                        'margin_header'        => 0,
                                        'margin_footer'        => 0,
                                        'orientation'          => 'P',
                                        'title'                => 'Thermal Invoice Printer',
                                        'author'               => '',
                                        'watermark'            => 'SimpleRetailPos',
                                        'show_watermark'       => true,
                                        'watermark_font'       => 'sans-serif',
                                        'display_mode'         => 'fullpage',
                                        'watermark_text_alpha' => 0.1
                                    ]);
                    $mpdf->SetDisplayMode('fullpage');
                    $mpdf->SetTitle('INV-'.$tab_invoice->id);
                    $stylesheet=file_get_contents(url('pdf/thermal.css'));
                    $stylesheet2=file_get_contents(url('assets/css/style.css'));

                    $mpdf->WriteHTML($stylesheet, 1);
                    $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
                    $mpdf->WriteHTML($thermalHtml, 2);
                    $mpdf->Output('invoice_' . time() . '.pdf', 'I');
                    exit();
                  
                }
                else
                {

                    $mpdf = new Mpdf([
                                        'mode' => '', 
                                        'orientation'          => 'P',
                                        'title'                => 'POS Invoice Printer',
                                        'author'               => '',
                                        'watermark'            => 'SimpleRetailPos',
                                        'show_watermark'       => true,
                                        'watermark_font'       => 'sans-serif',
                                        'display_mode'         => 'fullpage',
                                        'watermark_text_alpha' => 0.1
                                    ]);
                    $mpdf->SetDisplayMode('fullpage');
                    
                    $mpdf->SetTitle('INV-'.$tab_invoice->id);
                    $stylesheet=file_get_contents(url('assets/css/bootstrap.min.css'));
                    $stylesheet2=file_get_contents(url('assets/css/style.css'));
                    $html='<div class="container" id="report_container" style="border: 1px #ccc solid;">
                        <table  class="col-md-12" cellpadding="10" style="width:100%;" width="100%;">
                            <tr>
                            <td valign="top" width="200">
                        <div class="col-lg-3">
                            <div class="col-md-12" style="border-bottom: 5px #000 solid; font-size: 20px; font-weight: bold; padding-left: 0px;">
                                ' . formatDateTime($tab_invoice->created_at) . '<hr style="height:5px;">
                            </div>
                            <div class="col-md-12" style="padding-top: 20px; padding-bottom: 4px; color: #000; padding-left: 0px;">
                                <b><br />Customer Info</b>
                            </div>
                            <div class="col-md-12" style="padding-top: 4px; font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                                ' . $customer->name . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->address . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->email . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->phone . '
                            </div>

                            <div class="col-md-12" style="padding-top: 21px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                                <b><br />Ship To :</b>
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                                ' . $customer->name . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->address . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->email . '
                            </div>
                            <div class="col-md-12" style="padding-top: 4px;  font-size:12px; padding-bottom: 4px;  color: #008000; padding-left: 0px;">
                                ' . $customer->phone . '
                            </div>


                            <div class="col-md-12" style="padding-top: 35px; padding-bottom: 5px; padding-left: 0px; font-size: 15px;">
                                <b><br />Payment Method :</b>
                            </div>
                            <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; padding-left: 0px;">
                                ' . $tab_invoice->tender . '
                    </div>

                    <div class="col-md-12" style="padding-top: 30px; padding-bottom: 4px; padding-left: 0px;">
                    <img src="'.url('company/'.$invInfo->logo).'" style="width:100px; margin-top:10px;">
                    </div>

                    </div>
                    </td>
                    <td valign="top">
                    <div class="col-lg-9" style="float:left; margin-top:-50px;">
                    <div class="col-md-12" style="border-bottom: 5px #000 solid; color: #008000; font-size: 20px; font-weight: bold; padding-left: 0px;">
                    '.$invInfo->company_name.'<hr style="height:5px;">
                    </div>
                    <div class="col-md-12" style="padding-top: 10px; padding-bottom: 5px; padding-left: 0px; font-size: 13px;">
                    <b>'.$invInfo->company_thank_you_message.'<br /></b>
                    </div>
                    <div class="col-md-12" style="padding-top: 4px; padding-bottom: 4px; color: #008000; font-size: 10px; padding-left: 0px;">'.$invInfo->company_services.'
                    <br /><br />
                    </div>
                    <div class="col-md-12" style="padding-top: 4px;  padding-bottom: 4px; padding-left: 0px;">
                    <table class="table table-bordered" style="width:100%;">
                    <thead>
                    <tr>
                    <th class="text-center" style="font-size:12px;" >Item Number</th>
                    <th class="text-center" style="font-size:12px;" >Description</th>
                    <th class="text-center" style="font-size:12px;" >Price</th>
                    <th class="text-center" style="font-size:12px;" >Quantity</th>
                    <th class="text-center" style="font-size:12px;" >Amount</th>
                    </tr>
                    </thead>
                    <tbody>';

                    $subTotal=0;
                    $total=0;
                    $tender='';
                    $ai=0;
                    $ai_quantity=0;
                    if(isset($tab_invoice_product)){
                        foreach($tab_invoice_product as $inv){
                            $html .='<tr>
                        <td style="font-size:12px;" class="text-center">' . $inv->product_id . '</td>
                        <td style="font-size:12px; width:200px;">' . $inv->product_name . '</td>
                        <td style="font-size:12px;" class="text-center">' . $inv->price . '</td>
                        <td style="font-size:12px;" class="text-center">' . $inv->quantity . '</td>
                        <td style="font-size:12px;" class="text-right">' . number_format($inv->total_price, 2) . '</td>
                        </tr>';

                            $ai_quantity+=$inv->quantity;
                            $ai+=1;
                        }
                    }

                    for ($i=1; $i <= 16 - $ai; $i++):
                        $html .='<tr>
                    <td>&nbsp;
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    </tr>';
                    endfor;
                    $html .='<tr style="border-bottom: 5px #000 solid;">
                    <td style="font-size:12px;">Subtotal </td>
                    <td style="font-size:12px;">Total Item : ' . $ai_quantity . '</td>
                    <td></td>
                    <td></td>
                    <td style="font-size:12px;" class="text-right">' . number_format((($tab_invoice->total_amount+$tab_invoice->sales_discount)-$tab_invoice->total_tax), 2) . '</td>
                    </tr>

                    </tbody>
                    <tfoot>
                    <tr>
                    <td colspan="2" rowspan="5" style="font-size:10px;"> Sales Tax Rate: ' . number_format($tab_invoice->tax_rate, 2) . '% <br><br>';

                    if($tab_invoice->discount_type==1)
                    {
                        $html .='Discount Amount is '.number_format($tab_invoice->discount_total, 2);
                    }
                    elseif($tab_invoice->discount_type==2)
                    {
                        $html .='Discount Rate : '.number_format($tab_invoice->sales_discount, 2).'%';
                    }

                    $html .='</td>
                    <td colspan="2" style="font-size:10px;" class="text-right">Sales Tax (+)</td>
                    <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->total_tax, 2) . '</td>
                    </tr>
                    <tr>
                    <td style="font-size:10px;" colspan="2" class="text-right">Discount (-)</td>
                    <td style="font-size:12px;" class="text-right">' . number_format($tab_invoice->discount_total, 2) . '</td>
                    </tr>
                    <tr>
                    <td style="font-size:10px;" colspan="2" class="text-right">Invoice Total</td>
                    <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($tab_invoice->total_amount, 2) . '</td>
                    </tr>
                    <tr>
                    <td style="font-size:10px;" colspan="2" class="text-right">Paid Amount</td>
                    <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;">' . number_format($invoice_payment, 2) . '</td>
                    </tr>
                    <tr>
                    <td style="font-size:10px;" colspan="2" class="text-right">Invoice Due</td>
                    <td style="font-size:12px;" class="text-right" style="border-bottom: 5px #000 solid;"><b>'; 

                    if(($tab_invoice->total_amount-$invoice_payment)>0)
                    {
                        $html .=number_format(($tab_invoice->total_amount-$invoice_payment), 2);
                    }
                    else
                    {
                        $html .="0.00";
                    }
               
                    $html .='</b></td>
                    </tr>
                    </tfoot>


                    </table>
                    </div>
                    </div>
                    </td>
                    <tr>
                    </table>
                    <div class="row">
                    <div class="col-lg-12" style="padding-left: 5px; padding-top: 1px; margin-top:-20px;">
                    <div class="col-md-12 text-center">
                    <b>'.$invInfo->mm_one.'</b>
                    </div>
                    <div class="col-md-12 text-center">
                    <b>'.$invInfo->mm_two.'</b>
                    </div>
                    <div class="col-md-12 text-center">
                    <b>'.$invInfo->mm_three.'</b>
                    </div>
                    <div class="col-md-12 text-center">
                    <b>'.$invInfo->mm_four.'</b>
                    </div>
                    <br />
                    <table width="100%" style="margin-left:25px;" class="col-md-12">
                    <tr>
                    <td>
                    <div class="col-md-11 text-left" style="padding-left: 0px; border-bottom: 3px #000 solid; font-size: 28px; color: #008000;">
                    <b>
                    '.$invInfo->fotter_company_name.'</b><hr style="height:5px; margin-top:0px;">
                    </div>
                    </td>
                    <td width="50">
                    <img width="50" src="'.url('company/'.$invInfo->logo_fotter).'">
                    </td>
                    </tr>
                    </table>


                    <div class="col-md-12" style="clear: both;">
                    <br />
                    <table width="100%" class="col-md-12">
                    <tr>
                    <td style="text-align:center; font-size:10px;">
                    '.$invInfo->c_one.'
                    </td>
                    <td style="text-align:center; font-size:10px;">
                    <div class="col-md-5 text-center">
                    '.$invInfo->c_two.'
                    </div>
                    </td>
                    <td style="text-align:center; font-size:10px;">
                    <div class="col-md-4 text-center">
                    '.$invInfo->c_three.'
                    </div>
                    </td>
                    </tr>
                    <tr>
                    <td style="text-align:center; font-size:10px;">
                    <div class="col-md-3 text-center">
                    '.$invInfo->c_four.'
                    </div>
                    </td>
                    <td style="text-align:center; font-size:10px;">
                    <div class="col-md-5 text-center">
                    '.$invInfo->c_five.'
                    </div>
                    </td>
                    <td style="text-align:center; font-size:10px;">
                    <div class="col-md-4 text-center">
                    '.$invInfo->c_six.'
                    </div>
                    </td>
                    </tr>
                    </tr>
                    </table>
                    </div>
                    <div class="col-md-12" style="border-bottom: 5px #000 solid; margin-left:15px; clear: both;">
                    <hr style="height:5px; margin-top:5px;">
                    </div>
                    </div>
                    </div>
                    </div>';

                    //echo $html; die();

                    $mpdf->WriteHTML($stylesheet, 1);
                    $mpdf->WriteHTML($stylesheet2, 1); // The parameter 1 tells that this is css/style only and no body/html/text
                    $mpdf->WriteHTML($html, 2);
                    $mpdf->Output('invoice_' . time() . '.pdf', 'I');
                    exit();


                }
            
        }
        else
        {
            return redirect('sales/report')->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }



    }

    public function showCustomerInvoice(Invoice $invoice,$invoice_id=0)
    {
        if(!empty($invoice_id))
        {



            $tab_invoice=$invoice::Leftjoin('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          "tenders.name as tender",
                                          'invoices.store_id',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.invoice_id',$invoice_id)
                                 ->first();
            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 //->groupBy("invoice_id")
                                 ->sum('paid_amount');

            //print_r($invoice_payment);   die();                  

            $tab_customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               ->select('invoice_products.*','products.name as product_name')
                                               ->get();

            $chkEmailInvoice=AuthorizeNetPayment::where('store_id',$tab_invoice->store_id)
                                                ->where('active_module_for_email_invoice',1)
                                                ->count();

            $chkAuthorizeNetPayment=AuthorizeNetPayment::where('store_id',$tab_invoice->store_id)
                                                ->where('active_module_for_email_invoice',1)
                                                ->count();

            //echo $tab_invoice->store_id; die();
            $authorizeNettender=Tender::where('authorizenet',1)->get();
            $payPaltender=Tender::where('paypal',1)->get();
            $InvInfo=$this->sdc->Invlayout($tab_invoice->store_id);
            if($this->sdc->InvoiceLayout($tab_invoice->store_id)==1)
            {
                return view('invoiceapp.pages.sales.invoice-template-one',
                [
                    'customer'=>$tab_customer,
                    'invoice'=>$tab_invoice,
                    'invoice_product'=>$tab_invoice_product,
                    'invoice_payment'=>$invoice_payment,
                    'authorizeNettender'=>$authorizeNettender,
                    'payPaltender'=>$payPaltender,
                    'chkEmailInvoice'=>$chkEmailInvoice,
                    'chkAuthorizeNetPayment'=>$chkAuthorizeNetPayment,
                    'invInfo'=>$InvInfo,
                    'invoice_id'=>$invoice_id
                ]);   
            }
            else
            {
                return view('invoiceapp.pages.sales.invoice-template-two',
                [
                    'customer'=>$tab_customer,
                    'invoice'=>$tab_invoice,
                    'invoice_product'=>$tab_invoice_product,
                    'invoice_payment'=>$invoice_payment,
                    'authorizeNettender'=>$authorizeNettender,
                    'payPaltender'=>$payPaltender,
                    'chkEmailInvoice'=>$chkEmailInvoice,
                    'chkAuthorizeNetPayment'=>$chkAuthorizeNetPayment,
                    'invInfo'=>$InvInfo,
                    'invoice_id'=>$invoice_id
                ]);    
            }

            
        }
        else
        {
            return redirect('sales/report')->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }
    }

    public function invoiceShow(Invoice $invoice,$invoice_id=0)
    {
        if(!empty($invoice_id))
        {
            $tab_invoice=$invoice::Leftjoin('tenders','invoices.tender_id','=','tenders.id')
                                 ->select('invoices.id',
                                          'invoices.tax_rate',
                                          'invoices.total_tax',
                                          'invoices.discount_type',
                                          'invoices.sales_discount',
                                          'invoices.discount_total',
                                          'invoices.total_amount',
                                          'invoices.invoice_id',
                                          'tenders.name as tender',
                                          'invoices.created_at',
                                          'invoices.customer_id')
                                 ->where('invoices.id',$invoice_id)
                                 ->where('invoices.store_id',$this->sdc->storeID())
                                 ->first();

            

            $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 ->where('store_id',$this->sdc->storeID())
                                 //->groupBy("invoice_id")
                                 ->sum('paid_amount');

            //print_r($invoice_payment);   die();                  

            $tab_customer=Customer::find($tab_invoice->customer_id);


            $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                               ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                               ->where('invoice_products.store_id',$this->sdc->storeID())
                                               ->select('invoice_products.*','products.name as product_name')
                                               ->get();
            $InvInfo=$this->sdc->Invlayout();
            if($this->sdc->InvoiceLayout()==1)
            {
                return view('apps.pages.sales.invoice-template-one',
                [
                    'customer'=>$tab_customer,
                    'invoice'=>$tab_invoice,
                    'invoice_product'=>$tab_invoice_product,
                    'invoice_payment'=>$invoice_payment,
                    'invInfo'=>$InvInfo
                ]);   
            }
            else
            {
                return view('apps.pages.sales.invoice-template-two',
                [
                    'customer'=>$tab_customer,
                    'invoice'=>$tab_invoice,
                    'invoice_product'=>$tab_invoice_product,
                    'invoice_payment'=>$invoice_payment,
                    'invInfo'=>$InvInfo
                ]);    
            }

            
        }
        else
        {
            return redirect('sales/report')->with('error', $this->moduleName.' Invoice failed to load, Please try again. !'); 
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $tab_tender=Tender::where('store_id',$this->sdc->storeID())->get();
        $tab_customer=Customer::where('store_id',$this->sdc->storeID())->get();
        return view('apps.pages.sales.confirm-sales',['customerData'=>$tab_customer,'tenderData'=>$tab_tender,'req_pid'=>$request->pid,'req_quantity'=>$request->quantity,'req_name'=>$request->name,'req_price'=>$request->price]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


    public function profitQuery($request)
    {
        $invoice=invoice::join('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->orderBy("invoices.id","DESC")
                     ->get();

        return $invoice;
    }

    public function exportExcel(Request $request) 
    {
        echo "string"; die();
        //excel 
        $data=array();
        $array_column=array('ID','Invoice ID','Invoice Date','Sold To','Invoice Total Amount');
        array_push($data, $array_column);
        $inv=$this->profitQuery($request);

        dd($inv);
        foreach($inv as $voi):
            $inv_arry=array($voi->id,$voi->invoice_id,$voi->created_at,$voi->customer_name,$voi->total_amount);
            array_push($data, $inv_arry);
        endforeach;

        $reportName="Sales Report";
        $report_title="Sales Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));
        $excelArray=array(
            'report_name'=>$reportName,
            'report_title'=>$report_title,
            'report_description'=>$report_description,
            'data'=>$data
        );

        $this->sdc->ExcelLayout($excelArray);
        
    }

    public function salesPDF(Request $request)
    {

        $data=array();      
        $reportName="Sales Report";
        $report_title="Sales Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));

        $html='<table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >ID</th>
                <th class="text-center" style="font-size:12px;" >Invoice ID</th>
                <th class="text-center" style="font-size:12px;" >Invoice Date</th>
                <th class="text-center" style="font-size:12px;" >Sold To</th>
                <th class="text-center" style="font-size:12px;" width="10%">Invoice Total Amount</th>
                </tr>
                </thead>
                <tbody>';

                    $inv=$this->profitQuery($request);
                    foreach($inv as $voi):
                        $html .='<tr>
                        <td style="font-size:12px;" class="text-center">'.$voi->id.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->invoice_id.'</td>
                        <td style="font-size:12px;" class="text-center">'.formatDateTime($voi->created_at).'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->customer_name.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->total_amount.'</td>
                        </tr>';

                    endforeach;


                        

             
                /*html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : 4</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">00</td>
                </tr>';*/

                $html .='</tbody>
                
                </table>


                ';



                $this->sdc->PDFLayout($reportName,$html);


    }


    public function store(Request $request)
    {
       //echo "<pre>";
       
       $this->validate($request,[
            'customer_id'=>'required',
            'tender_id'=>'required'
        ]);

        $tender_id=$request->tender_id;
        if(empty($tender_id))
        {
            $tender_id=0;
        }

        $tenderData=Tender::find($tender_id);


        $tender_name=$tenderData->name;

        

        $invoice_id=time();
        $total_amount_invoice=0;
        $total_cost_invoice=0;
        $total_profit_invoice=0;
        $total_sold_quantity=0;
        foreach($request->pid as $key=>$pid):
            $pro=Product::find($pid);
            $tab_stock=new InvoiceProduct;
            $tab_stock->invoice_id=$invoice_id;
            $tab_stock->product_id=$pid;
            $tab_stock->quantity=$request->quantity[$key];
            $tab_stock->price=$pro->price;
            $tab_stock->cost=$pro->cost;
            $tab_stock->total_price=($request->quantity[$key]*$pro->price);
            $tab_stock->total_cost=($request->quantity[$key]*$pro->cost);
            $tab_stock->store_id=$this->sdc->storeID();
            $tab_stock->created_by=$this->sdc->UserID();
            $tab_stock->save();

            Product::where('id',$pid)
            ->update([
               'quantity' => \DB::raw('quantity - '.$request->quantity[$key]),
               'sold_times' => \DB::raw('sold_times + 1')
            ]);

            $amount_invoice=($request->quantity[$key]*$pro->price);
            $cost_invoice=($request->quantity[$key]*$pro->cost);
            $profit_invoice=$amount_invoice-$cost_invoice;

            $total_amount_invoice+=$amount_invoice;
            $total_cost_invoice+=$cost_invoice;
            $total_profit_invoice+=$profit_invoice;
            $total_sold_quantity+=$request->quantity[$key];
        endforeach;

        $sqlTender=Tender::find($request->tender_id);
        $tender_name="";
        $invoiceStatus="Due";
        if(isset($sqlTender))
        {
            $tender_name=$sqlTender->name?$sqlTender->name:'';
            if(isset($tender_name))
            {
                if(!empty($tender_name))
                {
                    $invoiceStatus="Paid";
                }
                
            }
        }

        $tab=new Invoice;
        $tab->invoice_id=$invoice_id;
        $tab->customer_id=$request->customer_id;
        $tab->tender_id=$request->tender_id;
        $tab->tender_name=$tender_name;
        $tab->invoice_status=$invoiceStatus;
        $tab->total_amount=$total_amount_invoice;
        $tab->total_cost=$total_cost_invoice;
        $tab->total_profit=$total_profit_invoice;
        $tab->store_id=$this->sdc->storeID();
        $tab->created_by=$this->sdc->UserID();
        $tab->save();

        $tabCus=Customer::find($request->customer_id);
        $tabCus->last_invoice_no=$invoice_id;
        $tabCus->save();

        $this->sdc->log("sales","Invoice Created, Invoice ID : ".$invoice_id);

        RetailPosSummary::where('id',1)
        ->update([
           'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
           'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
           'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
           'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
           'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice),
           'product_quantity' => \DB::raw('product_quantity - '.$total_sold_quantity)
        ]);

        $Todaydate=date('Y-m-d');
        if(RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==0)
        {
            RetailPosSummaryDateWise::insert([
               'report_date'=>$Todaydate,
               'sales_invoice_quantity' => \DB::raw('1'),
               'sales_quantity' => \DB::raw($total_sold_quantity),
               'sales_amount' => \DB::raw($total_amount_invoice),
               'sales_cost' => \DB::raw($total_cost_invoice),
               'sales_profit' => \DB::raw($total_profit_invoice)
            ]);
        }
        else
        {
            RetailPosSummaryDateWise::where('report_date',$Todaydate)
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
               'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice)
            ]);
        }


        return redirect('sales')->with('status', $this->moduleName.' Genarated Successfully !'); 
    }


    public function CompleteSalesPOS(Request $request)
    {
       //echo "<pre>";
       $cart = Session::has('Pos') ? Session::get('Pos') : null;
       //dd($cart->items);


       $countItems=count($cart->items);
       
       $total_amount_invoice=0;
       $total_cost_invoice=0;
       $total_profit_invoice=0;
       $total_sold_quantity=0;

       $discount_type=0;
       $discount_amount=0;
       $discount_total=0;

       $customer_id=$cart->customerID;
       $customerInfo=Customer::find($customer_id);
       $customerName=$customerInfo->name;

       $paid_cart=$cart->paid;
       $totalPrice_cart=$cart->totalPrice;

       

       $ivP="Pending";
       $DFGinvoicePaymentStatus=$cart->paymentMethodID;

       if(!empty($DFGinvoicePaymentStatus))
        {
            //echo $paid_cart."    ".$totalPrice_cart; die();
            if($paid_cart>$totalPrice_cart)
            {
                $ivP="Paid";
            }
            elseif($paid_cart==$totalPrice_cart)
            {
                $ivP="Paid";
            }
            else
            {
                $ivP="Partial";
            }
            
        }

        //print_r("PPPP".$ivP); die();


       if($countItems>0)
       {
            $invoice_id=$cart->invoiceID;
            if(empty($invoice_id))
            {
                $invoice_id=time();
            }

            foreach($cart->items as $row):

                
                if(isset($row['repair']))
                {
                    \DB::table('in_store_repairs')->where('id',$row['repair'])
                                                  ->update(['invoice_id'=>$invoice_id,'payment_status'=>$ivP]);
                }
                elseif(isset($row['ticket']))
                {
                    \DB::table('in_store_tickets')->where('id',$row['ticket'])
                                                  ->update(['invoice_id'=>$invoice_id,'payment_status'=>$ivP]);
                }
                elseif(isset($row['repairArray']))
                {

                    $repairArray=$row['repairArray'];
                    $device_id=$repairArray['device_id'];
                    $model_id=$repairArray['model_id'];
                    $problem_id=$repairArray['problem_id'];

                    $device_info=\DB::table('in_store_repair_devices')->where('id',$device_id)->first();
                    $device_name=$device_info->name;

                    $model_info=\DB::table('in_store_repair_models')->where('id',$model_id)->first();
                    $model_name=$model_info->name;

                    $problem_info=\DB::table('in_store_repair_problems')->where('id',$problem_id)->first();
                    $problem_name=$problem_info->name;

                    $price=0;
                    if(!empty($repairArray['override_repair_price']))
                    {
                        $price=$repairArray['override_repair_price'];
                    }
                    else
                    {
                        $price=$repairArray['repair_price'];
                    }



                    $tab=new InStoreRepair();
                    $tab->customer_id=$customer_id;
                    $tab->customer_name=$customerName;
                    $tab->device_id=$repairArray['device_id'];
                    $tab->device_name=$device_name;
                    $tab->model_id=$repairArray['model_id'];
                    $tab->model_name=$model_name;
                    $tab->problem_id=$repairArray['problem_id'];
                    $tab->problem_name=$problem_name;
                    $tab->price=$price;
                    $tab->repair_price=$repairArray['repair_price'];
                    $tab->override_repair_price=$repairArray['override_repair_price'];
                    $tab->password=$repairArray['repair_password'];
                    $tab->imei=$repairArray['repair_imei'];
                    $tab->tested_before_by=$repairArray['repair_tested_before_by'];
                    $tab->tested_after_by=$repairArray['repair_tested_after_by'];
                    $tab->tech_notes=$repairArray['repair_tech_notes'];
                    $tab->how_did_you_hear_about_us=$repairArray['repair_how_did_you_hear_about_us'];
                    $tab->start_time=$repairArray['repair_start_time'];
                    $tab->end_time=$repairArray['repair_end_time'];
                    if(isset($repairArray['repair_salvage_part']))
                    {
                        $tab->salvage_part=$repairArray['repair_salvage_part'];
                    }
                    

                    unset($repairArray['device_id']);
                    unset($repairArray['model_id']);
                    unset($repairArray['problem_id']);
                    unset($repairArray['repair_price']);
                    unset($repairArray['override_repair_price']);
                    unset($repairArray['repair_password']);
                    unset($repairArray['repair_imei']);
                    unset($repairArray['repair_tested_before_by']);
                    unset($repairArray['repair_tested_after_by']);
                    unset($repairArray['repair_tech_notes']);
                    unset($repairArray['repair_how_did_you_hear_about_us']);
                    unset($repairArray['repair_start_time']);
                    unset($repairArray['repair_end_time']);
                    if(isset($repairArray['repair_salvage_part']))
                    {
                        unset($repairArray['repair_salvage_part']);
                    }
                    $repair_json=json_encode($repairArray);

                    $productInfo=Product::find($row['item_id']);
                    $our_cost=$productInfo->cost;


                    
                    $tab->repair_json=$repair_json;
                    $tab->invoice_id=$invoice_id;
                    $tab->product_id=$row['item_id'];
                    $tab->product_name=$row['item'];
                    $tab->our_cost=$our_cost;
                    $tab->payment_status=$ivP;
                    $tab->store_id=$this->sdc->storeID();
                    $tab->created_by=$this->sdc->UserID();
                    $tab->save();

                    \DB::statement("call updateDailyRepair('".$this->sdc->UserID()."','".$this->sdc->storeID()."')");
                    


                }
                elseif(isset($row['ticketArray']))
                {
                    

                    $ticketArray=$row['ticketArray'];

                    $problem_id=$ticketArray['ticket_problem_id'];
                    if($problem_id=="TP0001")
                    {

                        $checkExProb=TicketProblem::where('store_id',$this->sdc->storeID())
                                                            ->where('name',$ticketArray['ticket_problem_name'])
                                                            ->count();
                        if($checkExProb==0)
                        {
                            $tab=new TicketProblem();
                            $tab->name=$ticketArray['ticket_problem_name'];
                            $tab->store_id=$this->sdc->storeID();
                            $tab->created_by=$this->sdc->UserID();
                            $tab->save();
                        }
                        else
                        {
                            $tab=TicketProblem::where('store_id',$this->sdc->storeID())
                                                            ->where('name',$ticketArray['ticket_problem_name'])
                                                            ->first();
                        }
                        

                        $problem_id=$tab->id;
                    }

                    $problem_info=TicketProblem::where('id',$problem_id)->first();
                    $problem_name=$problem_info->name;

                    //InStoreTicket



                    $tic=new InStoreTicket();
                    $tic->customer_id=$customer_id;
                    $tic->customer_name=$customerName;
                    $tic->ticket_id=$ticketArray['ticket_id'];
                    $tic->device_type=$ticketArray['ticket_device_type'];
                    $tic->problem_id=$problem_id;
                    $tic->problem_name=$problem_name;
                    $tic->our_cost=$ticketArray['ticket_our_cost'];
                    $tic->retail_price=$ticketArray['ticket_retail_price'];
                    $tic->password=$ticketArray['ticket_password'];
                    $tic->type_n_color=$ticketArray['ticket_type_n_color'];
                    $tic->carrier=$ticketArray['ticket_carrier'];
                    $tic->imei=$ticketArray['ticket_imei'];
                    $tic->how_did_you_hear_about_us=$ticketArray['ticket_how_did_you_hear_about_us'];
                    $tic->isdropoff=$ticketArray['isdropoff'];
                    $tic->tested_before_by=$ticketArray['ticket_tested_before_by'];
                    $tic->tested_after_by=$ticketArray['ticket_tested_after_by'];
                    $tic->tech_notes=$ticketArray['ticket_tech_notes'];

                    unset($ticketArray['ticket_id']);
                    unset($ticketArray['ticket_device_type']);
                    unset($ticketArray['ticket_problem_name']);
                    unset($ticketArray['ticket_problem_id']);
                    unset($ticketArray['ticket_our_cost']);
                    unset($ticketArray['ticket_retail_price']);
                    unset($ticketArray['ticket_password']);
                    unset($ticketArray['ticket_type_n_color']);
                    unset($ticketArray['ticket_carrier']);
                    unset($ticketArray['ticket_imei']);
                    unset($ticketArray['ticket_how_did_you_hear_about_us']);
                    unset($ticketArray['isdropoff']);
                    unset($ticketArray['ticket_tested_before_by']);
                    unset($ticketArray['ticket_tested_after_by']);
                    unset($ticketArray['ticket_tech_notes']);

                    $ticket_json=json_encode($ticketArray);

                    $tic->ticket_json=$ticket_json;
                    $tic->invoice_id=$invoice_id;
                    $tic->product_id=$row['item_id'];
                    $tic->product_name=$row['item'];
                    $tic->ticket_status="Pending";
                    $tic->payment_status=$ivP;
                    $tic->store_id=$this->sdc->storeID();
                    $tic->created_by=$this->sdc->UserID();
                    $tic->save();

                    \DB::statement("call updateDailyTicket('".$this->sdc->UserID()."','".$this->sdc->storeID()."')");

                   // dd($row['ticketArray']); die();

                }

                

                $pid=$row['item_id'];
                $quantity=$row['qty'];
                $unitprice=$row['unitprice'];
                $pro=Product::find($pid);
                $tab_stock=new InvoiceProduct;
                $tab_stock->invoice_id=$invoice_id;
                $tab_stock->product_id=$pid;
                $tab_stock->tax_percent=$cart->TaxRate;
                $tab_stock->tax_amount=$row['tax'];
                $tab_stock->quantity=$quantity;
                $tab_stock->price=$unitprice;
                $tab_stock->cost=$pro->cost;
                $tab_stock->total_price=($quantity*$unitprice);
                $tab_stock->total_cost=($quantity*$pro->cost);
                $tab_stock->store_id=$this->sdc->storeID();
                $tab_stock->created_by=$this->sdc->UserID();
                $tab_stock->save();

                Product::where('id',$pid)
                ->update([
                   'quantity' => \DB::raw('quantity - '.$quantity),
                   'sold_times' => \DB::raw('sold_times + 1')
                ]);

                $amount_invoice=($quantity*$unitprice);
                $cost_invoice=($quantity*$pro->cost);
                $profit_invoice=$amount_invoice-$cost_invoice;
                $total_amount_invoice+=$amount_invoice;
                $total_cost_invoice+=$cost_invoice;
                $total_profit_invoice+=$profit_invoice;
                $total_sold_quantity+=$quantity;



            endforeach;

            
            $discount_type=$cart->discount_type;
            $discount_amount=$cart->sales_discount;
            if(!empty($discount_type))
            {
                if(!empty($discount_amount))
                {
                    if($discount_type==1)
                    {
                        $discount_total=$discount_amount;
                    }
                    elseif($discount_type==2)
                    {
                        $discount_total=(($total_amount_invoice*$discount_amount)/100);
                    }
                }
            }

            

            $taxAmount=(($total_amount_invoice*$cart->TaxRate)/100);
            $total_amount_invoice-=$discount_total;
            $total_amount_invoice+=$taxAmount;

            $sqlTender=Tender::find($cart->paymentMethodID);
            $tender_name="";
            $invoiceStatus="Due";
            if(isset($sqlTender))
            {
                $tender_name=$sqlTender->name?$sqlTender->name:'';
                if(isset($cart->paid))
                {
                    if(!empty($cart->paid))
                    {
                        if($total_amount_invoice>$cart->paid)
                        {
                            $invoiceStatus="Partial";
                        }
                        elseif($total_amount_invoice==$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                        elseif($total_amount_invoice<=$cart->paid)
                        {
                            $invoiceStatus="Paid";
                        }
                    }
                    
                }
                
            }

            $tab=new Invoice;
            $tab->invoice_id=$invoice_id;
            $tab->customer_id=$cart->customerID;
            $tab->tender_id=$cart->paymentMethodID;
            $tab->tender_name=$tender_name;
            $tab->invoice_status=$invoiceStatus;
            $tab->tax_rate=$cart->TaxRate;
            $tab->total_tax=$taxAmount;
            $tab->discount_type=$discount_type;
            $tab->sales_discount=$discount_amount;
            $tab->discount_total=$discount_total;
            $tab->total_amount=$total_amount_invoice;
            $tab->total_cost=$total_cost_invoice;
            $tab->total_profit=$total_profit_invoice;
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
            $nid=$tab->id;

            \DB::statement("call updateCashierSalesSummary('".$this->sdc->UserID()."',
                                                        '".$total_amount_invoice."',
                                                        '".$this->sdc->storeID()."')");

            if($cart->customerID>0)
            {
                $tabCus=Customer::find($cart->customerID);
                $tabCus->last_invoice_no=$invoice_id;
                $tabCus->save();
                $customer_name=$tabCus->name;
            }
            

            

            $tabInPay=new InvoicePayment;
            $tabInPay->invoice_id=$invoice_id;
            $tabInPay->customer_id=$cart->customerID;
            $tabInPay->customer_name=$customer_name;
            $tabInPay->tender_id=$cart->paymentMethodID;
            $tabInPay->tender_name=$tender_name;
            $tabInPay->total_amount=$total_amount_invoice;
            $tabInPay->paid_amount=$cart->paid;
            $tabInPay->store_id=$this->sdc->storeID();
            $tabInPay->created_by=$this->sdc->UserID();
            $tabInPay->save();

            $this->sdc->log("sales","Invoice Created, Invoice ID : ".$invoice_id);

            RetailPosSummary::where('id',1)
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
               'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice),
               'product_quantity' => \DB::raw('product_quantity - '.$total_sold_quantity)
            ]);

            //updateCheckTodaySummary
            \DB::statement("call updateCheckTodaySummary('".$this->sdc->UserID()."','".$this->sdc->storeID()."','1','".$total_sold_quantity."','".$total_amount_invoice."','".$total_cost_invoice."','".$total_profit_invoice."','".$total_sold_quantity."')");

            $edQr=$this->sdc->invoiceEmailTemplate();
            $emaillayoutData=$edQr['editData'];
            $bcc=$emaillayoutData->bcc?$emaillayoutData->bcc:'';

            $tabsse=new SendSalesEmail;
            $tabsse->invoice_id=$invoice_id;
            $tabsse->email_address=$tabCus->email;
            $tabsse->bcc_email_address=$bcc;
            $tabsse->email_process_type=$emaillayoutData->email_time;
            $tabsse->store_id=$this->sdc->storeID();
            $tabsse->created_by=$this->sdc->UserID();
            $tabsse->save();

            $Ncart = new Pos($cart);
            $Ncart->ClearCart();
            Session::put('Pos', $Ncart);
            if($request->printData==1)
            {
                return response()->json($nid);
            }
            else
            {
                return response()->json(1);
            }
            
       }
       else
       {
            return response()->json(0);
       }
    }

    public function savePartialPaidInvoice(Request $request)
    {
        $invoice_id=$request->invoice_id;
        $paid_amount=$request->paid_amount;
        if(empty($invoice_id))
        {
            $returnArray=array('status'=>0,'msg'=>'Please Select a Invoice');
        }
        elseif(empty($request->payment_method_id))
        {
            $returnArray=array('status'=>0,'msg'=>'Please Select a Payment Method');
        }
        elseif(empty($paid_amount))
        {
            $returnArray=array('status'=>0,'msg'=>'Please Type a Today Paid Amount.');
        }
        else
        {

            $loadInvoices=Invoice::join('customers','invoices.customer_id','=','customers.id')
                                  ->select(
                                    'invoices.id',
                                    'invoices.invoice_id',
                                    'invoices.total_amount',
                                    'invoices.paid_amount',
                                    'customers.name as customer_name',
                                    \DB::Raw('CASE WHEN lsp_invoices.paid_amount = 0 THEN 
                                        (SELECT SUM(paid_amount) FROM lsp_invoice_payments WHERE lsp_invoice_payments.invoice_id=lsp_invoices.invoice_id)
                                    ELSE lsp_invoices.paid_amount END AS absPaid'),
                                    'invoices.created_at')
                                  ->where('invoices.store_id',$this->sdc->storeID())
                                  ->where('invoices.invoice_id',$invoice_id)
                                  ->whereRaw("lsp_invoices.invoice_status!='Paid'")
                                  ->first();

            $invoice=Invoice::where('invoice_id',$request->invoice_id)->first();
            $customer_id=$invoice->customer_id;
            $customer=Customer::find($customer_id);
            $customer_name=$customer->name;

            $load_total_amount=$loadInvoices->total_amount;
            $load_absPaid=$loadInvoices->absPaid+$paid_amount;
            $load_due=$load_total_amount-$load_absPaid;
            if($load_due>0)
            {
                $load_invoice_status="Partial";
            }
            elseif($load_due<=0)
            {
                $load_invoice_status="Paid";
                $load_due="0.00";
            }
            else
            {
                $load_invoice_status="Partial";
            }

            if(empty($invoice->tender_id))
            {
                $tender=Tender::find($request->payment_method_id);
                $tender_name=$tender->name;
                $tender_id=$tender->id;

                $invoice->tender_id=$tender_id;
                $invoice->tender_name=$tender_name;
                $invoice->save();
            }
            else
            {
                $tender_id=$invoice->tender_id;
                $tender=Tender::find($tender_id);
                $tender_name=$tender->name;
                $invoice->save();
            }

            $total_amount_invoice=$invoice->total_amount;

            $chkTicketInvoice=\DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$request->invoice_id)->count();
            if($chkTicketInvoice>0)
            {
                \DB::table('in_store_tickets')->where('store_id',$this->sdc->storeID())->where('invoice_id',$request->invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }

            $chkRepairInvoice=\DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$request->invoice_id)->count();
            if($chkRepairInvoice>0)
            {
                \DB::table('in_store_repairs')->where('store_id',$this->sdc->storeID())->where('invoice_id',$request->invoice_id)->update(['payment_status'=>$load_invoice_status]);
            }


            $tabInPay=new InvoicePayment;
            $tabInPay->invoice_id=$invoice_id;
            $tabInPay->customer_id=$customer_id;
            $tabInPay->customer_name=$customer_name;
            $tabInPay->tender_id=$tender_id;
            $tabInPay->tender_name=$tender_name;
            $tabInPay->total_amount=$total_amount_invoice;
            $tabInPay->paid_amount=$paid_amount;
            $tabInPay->store_id=$this->sdc->storeID();
            $tabInPay->created_by=$this->sdc->UserID();
            $tabInPay->save();

            if($load_due<=0)
            {
                $invoice->due_amount="0.00";
            }
            
            $invoice->invoice_status=$load_invoice_status;
            $invoice->save();

            $this->sdc->log("sales","Invoice Partial Payment Saved Invoice - ".$invoice_id.", Paid Amount - ".$paid_amount);

            $returnArray=array('status'=>1);
        }

        return response()->json($returnArray);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice,request $request)
    {
        $invoice_id='';
        if(isset($request->invoice_id))
        {
            $invoice_id=$request->invoice_id;
        }
        $customer_id='';
        if(isset($request->customer_id))
        {
            $customer_id=$request->customer_id;
        }
        // dd($customer_id);
        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $invoice_status='';
        if(isset($request->invoice_status))
        {
            $invoice_status=$request->invoice_status;
        }

        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(lsp_invoices.created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }

        $tab=$invoice::Leftjoin('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->when($invoice_id, function ($query) use ($invoice_id) {
                            return $query->where('invoices.invoice_id','=', $invoice_id);
                     })
                     ->when($invoice_status, function ($query) use ($invoice_status) {
                            return $query->where('invoices.invoice_status','=', $invoice_status);
                     })
                     ->when($customer_id, function ($query) use ($customer_id) {
                            return $query->where('invoices.customer_id','=', $customer_id);
                     })
                     ->when($dateString, function ($query) use ($dateString) {
                            return $query->whereRaw($dateString);
                     })
                     ->orderBy("invoices.id","DESC")
                     ->get();
         //dd($tab);      
        $tab_customer=Customer::where('store_id',$this->sdc->storeID())->get();            
        return view('apps.pages.sales.list',
            [
                'dataTable'=>$tab,
                'customer' =>$tab_customer,
                'invoice_id'=>$invoice_id,
                'invoice_status'=>$invoice_status,
                'customer_id'=>$customer_id,
                'start_date'=>$start_date,
                'end_date'=>$end_date
            ]);
    }    

     public function SalesReport(Request $request)
    {

        $invoice_id='';
        if(isset($request->invoice_id))
        {
            $invoice_id=$request->invoice_id;
        }
        $customer_id='';
        if(isset($request->customer_id))
        {
            $customer_id=$request->customer_id;
        }
        // dd($customer_id);
        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $invoice_status='';
        if(isset($request->invoice_status))
        {
            $invoice_status=$request->invoice_status;
        }
        // dd($invoice_status);
        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(lsp_invoices.created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }

        $tab=Invoice::Leftjoin('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->when($invoice_id, function ($query) use ($invoice_id) {
                            return $query->where('invoices.invoice_id','=', $invoice_id);
                     })
                     ->when($invoice_status, function ($query) use ($invoice_status) {
                            return $query->where('invoices.invoice_status','=', $invoice_status);
                     })
                     ->when($customer_id, function ($query) use ($customer_id) {
                            return $query->where('invoices.customer_id','=', $customer_id);
                     })
                     ->when($dateString, function ($query) use ($dateString) {
                            return $query->whereRaw($dateString);
                     })
                     ->get();

      // dd($tab);
        return $tab;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ProductStockin  $productStockin
     * @return \Illuminate\Http\Response
     */
    
    public function ExcelReport(Request $request) 
    {
        // dd($request);
        //excel 
        $data=array();
        $array_column=array('Invoice ID','Sold To','Tender','Status','Invoice Total Amount','Invoice Date');
        array_push($data, $array_column);
        $inv=$this->SalesReport($request);
        foreach($inv as $voi):
            $inv_arry=array($voi->invoice_id,$voi->customer_name,$voi->tender_name,$voi->invoice_status,$voi->total_amount,formatDate($voi->created_at));
            array_push($data, $inv_arry);
        endforeach;

        $reportName="Sales Report";
        $report_title="Sales Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));
        /*$data = array(
            array('data1', 'data2'),
            array('data3', 'data4')
        );*/

        //array_unshift($data,$array_column);

       // dd($data);

        $excelArray=array(
            'report_name'=>$reportName,
            'report_title'=>$report_title,
            'report_description'=>$report_description,
            'data'=>$data
        );

        $this->sdc->ExcelLayout($excelArray);
        
    }


    public function PdfReport(Request $request)
    {

        $data=array();
        
       
        $reportName="Stock In Order Report";
        $report_title="Stock In Order Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));

        $html='<table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >Invoice ID</th>
                <th class="text-center" style="font-size:12px;" >Sold To</th>
                <th class="text-center" style="font-size:12px;" >Tender</th>
                <th class="text-center" style="font-size:12px;" >Status</th>
                <th class="text-center" style="font-size:12px;" >Invoice Total Amount</th>
                <th class="text-center" style="font-size:12px;" >Invoice Date</th>
                </tr>
                </thead>
                <tbody>';



                    $inv=$this->SalesReport($request);
                    foreach($inv as $index=>$voi):
    
                        $html .='<tr>
                        <td>'.$voi->invoice_id.'</td>
                        <td>'.$voi->customer_name.'</td>
                        <td>'.$voi->tender_name.'</td>
                        <td>'.$voi->invoice_status.'</td>
                        <td align="center">'.$voi->total_amount.'</td>
                        <td>'.formatDateTime($voi->created_at).'</td>
                        </tr>';

                    endforeach;



                        

             
                /*html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : 4</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">00</td>
                </tr>';*/

                $html .='</tbody></table>';

                //echo $html; die();



                $this->sdc->PDFLayout($reportName,$html);


    }


    public function makeSalesReturn(Invoice $invoice)
    {
        $tab=$invoice::join('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->where('invoices.sales_return',0)
                     ->orderBy("invoices.id","DESC")
                     ->get();
        return view('apps.pages.sales.make-sales-return',['dataTable'=>$tab]);
    }

    public function makeSalesReturnShow(Request $request)
    {

        $invoice_id='';
        if(isset($request->invoice_id))
        {
            $invoice_id=$request->invoice_id;
        }

        $customer_id='';
        if(isset($request->customer_id))
        {
            $customer_id=$request->customer_id;
        }

        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }

        $tab=SalesReturn::where('store_id',$this->sdc->storeID())
                         ->when($invoice_id, function ($query) use ($invoice_id) {
                                return $query->where('invoice_id','=', $invoice_id);
                         })
                         ->when($customer_id, function ($query) use ($customer_id) {
                                return $query->where('customer_id','=', $customer_id);
                         })
                         ->when($dateString, function ($query) use ($dateString) {
                                return $query->whereRaw($dateString);
                         })
                         ->orderBy("id","DESC")
                         ->get();

        $customer=Customer::where('store_id',$this->sdc->storeID())->get();

        return view('apps.pages.sales.make-sales-return-list',[
            'dataTable'=>$tab,
            'invoice_id'=>$invoice_id,
            'customer'=>$customer,
            'customer_id'=>$customer_id,
            'start_date'=>$start_date,
            'end_date'=>$end_date
        ]);
    }

    public function makeSalesReturnShowQuery($request)
    {
        $invoice_id='';
        if(isset($request->invoice_id))
        {
            $invoice_id=$request->invoice_id;
        }

        $customer_id='';
        if(isset($request->customer_id))
        {
            $customer_id=$request->customer_id;
        }

        $start_date='';
        if(isset($request->start_date))
        {
            $start_date=$request->start_date;
        }

        $end_date='';
        if(isset($request->end_date))
        {
            $end_date=$request->end_date;
        }

        if(empty($start_date) && !empty($end_date))
        {
            $start_date=$end_date;
        }

        if(!empty($start_date) && empty($end_date))
        {
            $end_date=$start_date;
        }

        $dateString='';
        if(!empty($start_date) && !empty($end_date))
        {
            $dateString="CAST(created_at as date) BETWEEN '".$start_date."' AND '".$end_date."'";
        }

        $invoice=SalesReturn::where('store_id',$this->sdc->storeID())
                         ->when($invoice_id, function ($query) use ($invoice_id) {
                                return $query->where('invoice_id','=', $invoice_id);
                         })
                         ->when($customer_id, function ($query) use ($customer_id) {
                                return $query->where('customer_id','=', $customer_id);
                         })
                         ->when($dateString, function ($query) use ($dateString) {
                                return $query->whereRaw($dateString);
                         })
                         ->orderBy("id","DESC")
                         ->get();
        //dd($invoice);
        return $invoice;
    }

    public function exportExcelmakeSalesReturnShow(Request $request) 
    {
        //excel 
        $data=array();
        $array_column=array('Invoice ID','Sales Return Date','Return To','Sales Total','Return Amount','Return Note');
        array_push($data, $array_column);
        $inv=$this->makeSalesReturnShowQuery($request);

        foreach($inv as $voi):
            $inv_arry=array($voi->invoice_id,formatDate($voi->created_at),$voi->customer_name,$voi->invoice_total,$voi->sales_return_amount,$voi->sales_return_note);
            array_push($data, $inv_arry);
        endforeach;

        $reportName="Sales Return Report";
        $report_title="Sales Return Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));
        /*$data = array(
            array('data1', 'data2'),
            array('data3', 'data4')
        );*/

        //array_unshift($data,$array_column);

       // dd($data);

        $excelArray=array(
            'report_name'=>$reportName,
            'report_title'=>$report_title,
            'report_description'=>$report_description,
            'data'=>$data
        );
        // dd($excelArray);
        $this->sdc->ExcelLayout($excelArray);
        
    }

    public function exportPDFmakeSalesReturnShow(Request $request)
    {

        $data=array();
        
       
        $reportName="Sales Return Report";
        $report_title="Sales Return Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));

        $html='<table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >Invoice ID</th>
                <th class="text-center" style="font-size:12px;" >Sales Return Date</th>
                <th class="text-center" style="font-size:12px;" >Return To</th>
                <th class="text-center" style="font-size:12px;" >Sales Total</th>
                <th class="text-center" style="font-size:12px;" >Return Amount</th>
                <th class="text-center" style="font-size:12px;" >Return Note</th>
                </tr>
                </thead>
                <tbody>';

                    //$inv_arry=array($voi->invoice_id,$voi->created_at,$voi->customer_name,$voi->invoice_total,$voi->sales_return_amount,$voi->sales_return_note);
                    $inv=$this->makeSalesReturnShowQuery($request);
                    foreach($inv as $voi):
                        $html .='<tr>
                        <td style="font-size:12px;" class="text-center">'.$voi->invoice_id.'</td>
                        <td style="font-size:12px;" class="text-center">'.formatDate($voi->created_at).'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->customer_name.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->invoice_total.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->sales_return_amount.'</td>
                        <td style="font-size:12px;" class="text-right">'.$voi->sales_return_note.'</td>
                        </tr>';

                    endforeach;


                        

             
                /*html .='<tr style="border-bottom: 5px #000 solid;">
                <td style="font-size:12px;">Subtotal </td>
                <td style="font-size:12px;">Total Item : 4</td>
                <td></td>
                <td></td>
                <td style="font-size:12px;" class="text-right">00</td>
                </tr>';*/

                $html .='</tbody>
                
                </table>


                ';



                $this->sdc->PDFLayout($reportName,$html);


    }


    public function createSalesReturn(Invoice $invoice,$sales_id=0)
    {
        $check=$invoice::join('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->where('invoices.id',$sales_id)
                     ->count();
        if($check>0)
        {
            $tab=$invoice::join('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.store_id',$this->sdc->storeID())
                     ->where('invoices.id',$sales_id)
                     ->first();
            //dd($tab);
            return view('apps.pages.sales.make-sales-return-form',['ps'=>$tab]);
        }
        else
        {
            return redirect('sales/return/create')->with('error','Sales Return failed, Please try again.'); 
        }
        
    }

    public function storeSalesReturn(Invoice $invoice,Request $request,$sales_id=0)
    {
        $this->validate($request,[
            'return_amount'=>'required|numeric'
        ]);

        $tab=$invoice::find($sales_id);
        $tab->sales_return=1;
        $tab->save();

        $sr=new SalesReturn;
        $sr->invoice_id=$tab->invoice_id;
        $sr->customer_id=$tab->customer_id;
        $sr->customer_name=$request->customer_name;
        $sr->invoice_total=$tab->total_amount;
        $sr->sales_return_amount=$request->return_amount;
        $sr->sales_return_note=$request->sales_return_note;
        $sr->store_id=$this->sdc->storeID();
        $sr->created_by=$this->sdc->UserID();
        $sr->save();

        return redirect('sales/return/create')->with('status','Sales Return Completed Successfully.'); 
    }

  

    public function SaveSalesReturnInvoice(Request $request)
    {


        $tab=Invoice::where('invoice_id',$request->invoice_id)->first();
        $tab->sales_return=1;
        $tab->save();

        $customer=Customer::find($request->customer_id);

        $sr=new SalesReturn;
        $sr->invoice_id=$request->invoice_id;
        $sr->customer_id=$request->customer_id;
        $sr->customer_name=$customer->name;
        $sr->invoice_total=$request->sales_amount;
        $sr->sales_return_amount=$request->return_amount;
        $sr->sales_return_note=$request->sales_return_note;
        $sr->store_id=$this->sdc->storeID();
        $sr->created_by=$this->sdc->UserID();
        $sr->save();

        return response()->json(1); 
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function edit(Invoice $invoice,$id=0)
    {
        $tab_tender=Tender::where('store_id',$this->sdc->storeID())->get();
        $tab_customer=Customer::where('store_id',$this->sdc->storeID())->get();
        
        $tab_invoice=$invoice::where('id',$id)
                             ->where('store_id',$this->sdc->storeID())
                             ->first();

        $invoice_payment=InvoicePayment::where('invoice_id',$tab_invoice->invoice_id)
                                 ->where('store_id',$this->sdc->storeID())
                                 ->sum('paid_amount');

        $tab_invoice_product=InvoiceProduct::join('products','invoice_products.product_id','=','products.id')
                                           ->where('invoice_products.invoice_id',$tab_invoice->invoice_id)
                                           ->where('invoice_products.store_id',$this->sdc->storeID())
                                           ->select('invoice_products.*','products.name as product_name')
                                           ->get();


        return view('apps.pages.sales.edit-sales',
            [
                'customerData'=>$tab_customer,
                'tenderData'=>$tab_tender,
                'invoice'=>$tab_invoice,
                'invoice_product'=>$tab_invoice_product,
                'invoice_payment'=>$invoice_payment
            ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invoice $invoice,$id=0)
    {
        $this->validate($request,[
            'customer_id'=>'required',
            //'tender_id'=>'required'
        ]);

        $inv=Invoice::find($id);
        $invoice_id=$inv->invoice_id;
        $invoice_date=date('Y-m-d',strtotime($inv->created_at));
        $this->sdc->log("sales","Invoice Updated, Invoice ID : ".$invoice_id);
        $total_amount_invoice=0;
        $total_cost_invoice=0;
        $total_profit_invoice=0;
        $total_sold_quantity=0;
        foreach($request->sid as $key=>$sid):
            $sqlInv=InvoiceProduct::find($sid);
            $quantity=$sqlInv->quantity;
            $pid=$sqlInv->product_id;
            Product::where('id',$pid)
            ->update([
               'quantity' => \DB::raw('quantity + '.$quantity),
               'sold_times' => \DB::raw('sold_times - 1')
            ]);
            $total_amount_invoice+=($quantity*$sqlInv->price);
            $total_cost_invoice+=($quantity*$sqlInv->cost);
            $total_profit_invoice+=($quantity*$sqlInv->price)-($quantity*$sqlInv->cost);
            $total_sold_quantity+=$quantity;
        endforeach;  

        $discount_type=$inv->discount_type;
        $discount_amount=$inv->sales_discount;
        if(!empty($discount_type))
        {
            if(!empty($discount_amount))
            {
                if($discount_type==1)
                {
                    $discount_total=$discount_amount;
                }
                elseif($discount_type==2)
                {
                    $discount_total=(($total_amount_invoice*$discount_amount)/100);
                }
            }
        }

        

        $taxAmount=(($total_amount_invoice*$inv->tax_rate)/100);
        $total_amount_invoice-=$discount_total;
        $total_amount_invoice+=$taxAmount;

        RetailPosSummary::where('id',1)
        ->update([
           'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity - 1'),
           'sales_quantity' => \DB::raw('sales_quantity - '.$total_sold_quantity),
           'sales_amount' => \DB::raw('sales_amount - '.$total_amount_invoice),
           'sales_cost' => \DB::raw('sales_cost - '.$total_cost_invoice),
           'sales_profit' => \DB::raw('sales_profit - '.$total_profit_invoice),
           'product_quantity' => \DB::raw('product_quantity + '.$total_sold_quantity)
        ]);


        $Todaydate=date('Y-m-d');
        if((RetailPosSummaryDateWise::whereRaw('report_date=CAST(now() as date)')->count()==1) && ($invoice_date==$Todaydate))
        {
            RetailPosSummaryDateWise::whereRaw('report_date=CAST(now() as date)')
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity - 1'),
               'sales_quantity' => \DB::raw('sales_quantity - '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount - '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost - '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit - '.$total_profit_invoice)
            ]);
        }
  



        $inbTab=InvoiceProduct::where('store_id',$this->sdc->storeID())
                              ->where('invoice_id',$invoice_id)
                              ->delete();

        $total_amount_invoice=0;
        $total_cost_invoice=0;
        $total_profit_invoice=0;
        $total_sold_quantity=0;
        foreach($request->pid as $key=>$pid):
            $pro=Product::find($pid);
            $tab_stock=new InvoiceProduct;
            $tab_stock->invoice_id=$invoice_id;
            $tab_stock->product_id=$pid;
            $tab_stock->quantity=$request->quantity[$key];
            $tab_stock->price=$request->price[$key];
            $tab_stock->cost=$request->cost[$key];
            $tab_stock->total_price=($request->quantity[$key]*$request->price[$key]);
            $tab_stock->total_cost=($request->quantity[$key]*$request->cost[$key]);
            $tab_stock->store_id=$this->sdc->storeID();
            $tab_stock->created_by=$this->sdc->UserID();
            $tab_stock->updated_by=$this->sdc->UserID();
            $tab_stock->save();

            Product::where('id',$pid)
            ->update([
               'quantity' => \DB::raw('quantity - '.$request->quantity[$key]),
               'sold_times' => \DB::raw('sold_times + 1')
            ]);

            $amount_invoice=($request->quantity[$key]*$request->price[$key]);
            $cost_invoice=($request->quantity[$key]*$request->cost[$key]);
            $profit_invoice=$amount_invoice-$cost_invoice;

            $total_amount_invoice+=$amount_invoice;
            $total_cost_invoice+=$cost_invoice;
            $total_profit_invoice+=$profit_invoice;
            $total_sold_quantity+=$request->quantity[$key];
        endforeach;

        $discount_type=$inv->discount_type;
        $discount_amount=$inv->sales_discount;
        if(!empty($discount_type))
        {
            if(!empty($discount_amount))
            {
                if($discount_type==1)
                {
                    $discount_total=$discount_amount;
                }
                elseif($discount_type==2)
                {
                    $discount_total=(($total_amount_invoice*$discount_amount)/100);
                }
            }
        }

        

        $taxAmount=(($total_amount_invoice*$inv->tax_rate)/100);
        $total_amount_invoice-=$discount_total;
        $total_amount_invoice+=$taxAmount;

        $tender_id=$request->tender_id;
        if(empty($tender_id))
        {
            $tender_id=0;
            $tender_name="";
        }
        else
        {
            $tenderData=Tender::find($tender_id);
            $tender_name=$tenderData->name;
        }

        $tab=Invoice::find($id);
        $tab->invoice_id=$invoice_id;
        $tab->customer_id=$request->customer_id;
        $tab->tender_id=$request->tender_id;
        
        $tab->tax_rate=$inv->tax_rate;
        $tab->total_tax=$taxAmount;
        
        $tab->discount_type=$discount_type;
        $tab->sales_discount=$discount_amount;
        $tab->discount_total=$discount_total;
        
        $tab->total_amount=$total_amount_invoice;
        $tab->total_cost=$total_cost_invoice;
        $tab->total_profit=$total_profit_invoice;
        $tab->updated_by=$this->sdc->UserID();
        $tab->save();

        RetailPosSummary::where('id',1)
        ->update([
           'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity + 1'),
           'sales_quantity' => \DB::raw('sales_quantity + '.$total_sold_quantity),
           'sales_amount' => \DB::raw('sales_amount + '.$total_amount_invoice),
           'sales_cost' => \DB::raw('sales_cost + '.$total_cost_invoice),
           'sales_profit' => \DB::raw('sales_profit + '.$total_profit_invoice),
           'product_quantity' => \DB::raw('product_quantity - '.$total_sold_quantity)
        ]);

        

        \DB::statement("call updateCheckTodaySummary('".$this->sdc->UserID()."','".$this->sdc->storeID()."','1','".$total_sold_quantity."','".$total_amount_invoice."','".$total_cost_invoice."','".$total_profit_invoice."','".$total_sold_quantity."')");

        return redirect('sales/report')->with('status', $this->moduleName.' Changed / Updated Successfully !'); 
    }

    public function loadCustomerInvoice(Request $request)
    {
        $customer_id=$request->customer_id;
        $loadInvoices=Invoice::where('customer_id',$customer_id)->where('store_id',$this->sdc->storeID())->get();

        return response()->json($loadInvoices);
    }

    public function loadInvoiceOnly()
    {
        $loadInvoices=Invoice::select('id','invoice_id','total_amount','created_at')->where('store_id',$this->sdc->storeID())->get();

        return response()->json($loadInvoices);
    }

    public function loadPartialPaidInvoiceOnly()
    {
        $loadInvoices=Invoice::join('customers','invoices.customer_id','=','customers.id')
                              ->select(
                                'invoices.id',
                                'invoices.invoice_id',
                                'invoices.total_amount',
                                'invoices.invoice_status',
                                'invoices.paid_amount',
                                'customers.name as customer_name',
                                \DB::Raw('CASE WHEN lsp_invoices.paid_amount = 0 THEN 
                                    IFNULL((SELECT SUM(paid_amount) FROM lsp_invoice_payments WHERE lsp_invoice_payments.invoice_id=lsp_invoices.invoice_id),0)
                                ELSE IFNULL(lsp_invoices.paid_amount,0) END AS absPaid'),
                                'invoices.created_at')
                              ->where('invoices.store_id',$this->sdc->storeID())
                              ->whereRaw("lsp_invoices.invoice_status!='Paid'")
                              ->get();

        return response()->json($loadInvoices);
    }

    public function loadWarrantyProductInvoice(Request $request)
    {
        $invoice_id=$request->invoice_id;
        if(!empty($invoice_id))
        {


            $oldCart = Session::has('cart') ? Session::get('cart') : null;

            //dd();
            $arrayExProduct=array();
            $cartItems=array();
            if(!empty($oldCart))
            {
                if(count($oldCart->items)>0)
                {
                    foreach($oldCart->items as $cat):
                        $oldPID=$cat['old_product_id'];
                        array_push($arrayExProduct,$oldPID);
                    endforeach;
                }

                $cartItems=$oldCart->items;
            }
            

            //dd($arrayExProduct);

            $tabInvoiceProduct=InvoiceProduct::select('invoice_products.*','products.name as product_name')
                         ->join('products','invoice_products.product_id','=','products.id')
                         ->where('invoice_products.invoice_id',$invoice_id)
                         ->where('invoice_products.store_id',$this->sdc->storeID())
                         ->when($arrayExProduct, function ($query) use ($arrayExProduct) {
                                return $query->whereNotIn('invoice_products.product_id', $arrayExProduct);
                         })
                         ->get();

            
            $loadProducts=Product::select('id','name')->where('store_id',$this->sdc->storeID())->get();

            $retArray=array('status'=>1,'invProduct'=>$tabInvoiceProduct,'product'=>$loadProducts);
        }
        else
        {
            $retArray=array('status'=>0);
        }

        return response()->json($retArray);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoice $invoice,$id=0)
    {
        $tab=$invoice::find($id);
        $invoice_id=$tab->invoice_id;
        $invoice_date=date('Y-m-d',strtotime($tab->created_at));
        $sqlInvhh=InvoiceProduct::where('store_id',$this->sdc->storeID())
                                   ->where('invoice_id',$invoice_id)
                                   ->get();
        $total_amount_invoice=0;
        $total_cost_invoice=0;
        $total_profit_invoice=0;
        $total_sold_quantity=0;
        foreach($sqlInvhh as $sqlInv):
            $quantity=$sqlInv->quantity;
            $pid=$sqlInv->product_id;
            Product::where('id',$pid)
            ->update([
               'quantity' => \DB::raw('quantity + '.$quantity),
               'sold_times' => \DB::raw('sold_times - 1')
            ]);
            $total_amount_invoice+=($quantity*$sqlInv->price);
            $total_cost_invoice+=($quantity*$sqlInv->cost);
            $total_profit_invoice+=($quantity*$sqlInv->price)-($quantity*$sqlInv->cost);
            $total_sold_quantity+=$quantity;
        endforeach;  
        $this->sdc->log("sales","Invoice Deleted, Invoice ID : ".$invoice_id);
        RetailPosSummary::where('id',1)
        ->update([
           'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity - 1'),
           'sales_quantity' => \DB::raw('sales_quantity - '.$total_sold_quantity),
           'sales_amount' => \DB::raw('sales_amount - '.$total_amount_invoice),
           'sales_cost' => \DB::raw('sales_cost - '.$total_cost_invoice),
           'sales_profit' => \DB::raw('sales_profit - '.$total_profit_invoice),
           'product_quantity' => \DB::raw('product_quantity + '.$total_sold_quantity)
        ]);

        $Todaydate=date('Y-m-d');
        if((RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==1) && ($invoice_date==$Todaydate))
        {
            RetailPosSummaryDateWise::where('report_date',$Todaydate)
            ->update([
               'sales_invoice_quantity' => \DB::raw('sales_invoice_quantity - 1'),
               'sales_quantity' => \DB::raw('sales_quantity - '.$total_sold_quantity),
               'sales_amount' => \DB::raw('sales_amount - '.$total_amount_invoice),
               'sales_cost' => \DB::raw('sales_cost - '.$total_cost_invoice),
               'sales_profit' => \DB::raw('sales_profit - '.$total_profit_invoice)
            ]);
        }


        $invoice_tab=InvoiceProduct::where('store_id',$this->sdc->storeID())
                                   ->where('invoice_id',$invoice_id)
                                   ->delete();
        $tab->delete();
        return redirect('sales/report')->with('status', $this->moduleName.' Invoices Deleted Successfully !');
    }
}
