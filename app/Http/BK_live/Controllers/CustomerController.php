<?php

namespace App\Http\Controllers;

use App\Customer;
use App\Role;
use App\User;
use App\Store;
use App\RetailPosSummary;
use App\RetailPosSummaryDateWise;
use Illuminate\Http\Request;
use App\Invoice;
use Excel;
use Auth;
class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */



    private $moduleName="Customer";
    private $sdc;
    public function __construct(){ 
        $this->sdc = new StaticDataController(); 
    }

    public function user()
    {
        if(Auth::user()->user_type==1)
        {
            $storeList = Store::all();
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',1)->orderBy('id','DESC')->get();
        }
        elseif(Auth::user()->user_type==2)
        {
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',2)->orderBy('id','DESC')->get();
        }
        else
        {
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',3)->get();
        }

        if(Auth::user()->user_type==1)
        {
            return view('apps.pages.user.index',['role'=>$role,'storeList'=>$storeList]);
        }
        else
        {
            return view('apps.pages.user.index',['role'=>$role]);
        }
        
    }

    public function getCustomer($id=0)
    {
        $cus=array();
        if($id>0)
        {
            $tab=Customer::find($id);
            $cus=$tab;
        }
        return response()->json($cus);
    }

    public function userList()
    {
        if(Auth::user()->user_type==1)
        {
            $user = User::leftjoin('roles','users.user_type','=','roles.id')
                        ->select('users.*','roles.name as user_type_name')
                        //->where('users.store_id',$this->sdc->storeID())
                        ->get();
        }
        else
        {
            $user = User::leftjoin('roles','users.user_type','=','roles.id')
                        ->select('users.*','roles.name as user_type_name')
                        ->where('users.store_id',$this->sdc->storeID())->get();
        }
        return view('apps.pages.user.userlist',['dataTable'=>$user]);
    }

    public function userSave(Request $request)
    {

       if(Auth::user()->user_type==1)
        {
           $this->validate($request,[
                'user_type'=>'required',
                'store_id'=>'required',
                'name'=>'required',
                'address'=>'required',
                'phone'=>'required',
                'email'=>'required|string|email|max:255',
                'password' => 'min:4',
                'password_confirmation' => 'required_with:password|same:password|min:4'
            ]);
        }
        else
        {
           $this->validate($request,[
                'user_type'=>'required',
                'name'=>'required',
                'address'=>'required',
                'phone'=>'required',
                'email'=>'required|string|email|max:255',
                'password' => 'min:4',
                'password_confirmation' => 'required_with:password|same:password|min:4'
            ]); 
        }


        $tab=new User;
        $tab->name=$request->name;
        $tab->user_type=$request->user_type;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->password = \Hash::make($request->password);
        $tab->remember_token=$request->_token;
        if(Auth::user()->user_type==1)
        {
            $tab->store_id=$request->store_id;
        }
        else
        {
            $tab->store_id=$this->sdc->storeID();
        }
        
        $tab->created_by=$this->sdc->UserID();
        $tab->save();

        if(Auth::user()->user_type==1)
        {
            $this->sdc->log("User","User account created for shop #".$tab->store_id.".");
        }
        else
        {
            $this->sdc->log("User","User account created.");
        }
        

        return redirect('user')->with('status', $this->moduleName.' Added Successfully !');
    }
    public function UserShow($id)
    {
        
        
        $edit = User::find($id);
        if(Auth::user()->user_type==1)
        {
            $storeList = Store::all();
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',1)->orderBy('id','DESC')->get();
            return view('apps.pages.user.index',['edit'=>$edit,'role'=>$role,'storeList'=>$storeList]);
        }
        elseif(Auth::user()->user_type==2)
        {
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',2)->orderBy('id','DESC')->get();
            return view('apps.pages.user.index',['edit'=>$edit,'role'=>$role]);
        }
        else
        {
            $role=\DB::table('roles')->select('id','name','store_id')->where('id','>',3)->get();
            return view('apps.pages.user.index',['edit'=>$edit,'role'=>$role]);
        }        
    }

    public function UserEdit(Customer $customer,$id=0)
    {
        $tab=$customer::find($id);
        $tabData=$customer::where('store_id',$this->sdc->storeID())->get();
        return view('apps.pages.customer.customer',['dataRow'=>$tab,'dataTable'=>$tabData,'edit'=>true]);

    }

    public function userUpdate(Request $request, $id=0)
    {
        if(Auth::user()->user_type==1)
        {
            $this->validate($request,[
                'user_type'=>'required',
                'name'=>'required',
                'address'=>'required',
                'phone'=>'required',
                'store_id' => 'required',
                //'password_confirmation' => 'required_with:password|same:password|min:6'
            ]);


            if(!empty($request->password))
            {
                 $this->validate($request,[
                    'password' => 'min:4',
                    'password_confirmation' => 'required_with:password|same:password|min:4'
                ]);

            }
        }
        else
        {
            $this->validate($request,[
                'user_type'=>'required',
                'name'=>'required',
                'address'=>'required',
                'phone'=>'required',
                //'password' => 'min:6',
                //'password_confirmation' => 'required_with:password|same:password|min:6'
            ]);


            if(!empty($request->password))
            {
                 $this->validate($request,[
                    'password' => 'min:4',
                    'password_confirmation' => 'required_with:password|same:password|min:4'
                ]);

            }
        }

        $tab=User::find($id);
        $tab->name=$request->name;
        $tab->user_type=$request->user_type;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        if(!empty($request->password))
        {
            $tab->password = \Hash::make($request->password);
        }
        $tab->remember_token=$request->_token;
        if(Auth::user()->user_type==1)
        {
            $tab->store_id=$request->store_id;
        }
        else
        {
            $tab->store_id=$this->sdc->storeID();
        }
        
        $tab->created_by=$this->sdc->UserID();
        $tab->save();
        $this->sdc->log("User","User account updated.");
        return redirect('user/list')->with('status', $this->moduleName.' Updated Successfully !');

    }
    public function Userdestroy($id)
    {
        $tab=User::find($id);
        $tab->delete();
        
        $this->sdc->log("User","User account deleted.");

        return redirect('user/list')->with('status', $this->moduleName.' Deleted Successfully !');
    }
    public function index()
    {
        return view('apps.pages.customer.customer');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function profitQuery($request)
    {
        $invoice=Customer::where('store_id',$this->sdc->storeID())->get();

        return $invoice;
    }

    public function exportExcel(Request $request) 
    {
        //echo "string"; die();
        //excel 
        $data=array();
        $array_column=array('ID','Invoice ID','Name','Address','Phone','Email');
        array_push($data, $array_column);
        $inv=$this->profitQuery($request);
        foreach($inv as $voi):
            $inv_arry=array($voi->id,$voi->last_invoice_no,$voi->name,$voi->address,$voi->phone,$voi->email);
            array_push($data, $inv_arry);
        endforeach;

        $reportName="Customer Report";
        $report_title="Customer Report";
        $report_description="Report Genarated : ".date('d-M-Y H:i:s a');
        $excelArray=array(
            'report_name'=>$reportName,
            'report_title'=>$report_title,
            'report_description'=>$report_description,
            'data'=>$data
        );

        $this->sdc->ExcelLayout($excelArray);
        
    }

    public function invoicePDF(Request $request)
    {

        $data=array();      
        $reportName="Customer Report";
        $report_title="Customer Report";
        $report_description="Report Genarated : ".formatDateTime(date('d-M-Y H:i:s a'));

        $html='<table class="table table-bordered" style="width:100%;">
                <thead>
                <tr>
                <th class="text-center" style="font-size:12px;" >ID</th>
                <th class="text-center" style="font-size:12px;" >Invoice ID</th>
                <th class="text-center" style="font-size:12px;" >Name</th>
                <th class="text-center" style="font-size:12px;" >Address</th>
                <th class="text-center" style="font-size:12px;" >Phone</th>
                <th class="text-center" style="font-size:12px;" >Email</th>
                </tr>
                </thead>
                <tbody>';

                    $inv=$this->profitQuery($request);
                    foreach($inv as $voi):
                        $html .='<tr>
                        <td style="font-size:12px;" class="text-center">'.$voi->id.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->last_invoice_no.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->name.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->address.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->phone.'</td>
                        <td style="font-size:12px;" class="text-center">'.$voi->email.'</td>
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

        $this->validate($request,[
            'name'=>'required',
            'phone'=>'required',
        ]);


        $tab=new Customer;
        $tab->name=$request->name;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->store_id=$this->sdc->storeID();
        $tab->created_by=$this->sdc->UserID();
        $tab->save();

        RetailPosSummary::where('id',1)->update(['customer_quantity' => \DB::raw('customer_quantity + 1')]);
        $Todaydate=date('Y-m-d');
        if(RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==0)
        {
            RetailPosSummaryDateWise::insert([
               'report_date'=>$Todaydate,
               'customer_quantity' => \DB::raw('1')
            ]);
        }
        else
        {
            RetailPosSummaryDateWise::where('report_date',$Todaydate)
            ->update([
               'customer_quantity' => \DB::raw('customer_quantity + 1')
            ]);
        }

        $this->sdc->log("customer","Customer account created.");

        return redirect('customer')->with('status', $this->moduleName.' Added Successfully !');
    }
    public function posCustomerAdd(Request $request)
    {

       //echo "string"; die();
        $tab=new Customer;
        $tab->name=$request->name;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->store_id=$this->sdc->storeID();
        $tab->created_by=$this->sdc->UserID();
        $tab->save();

        

        return $tab->id;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    private function methodToGetMembersCount($search=''){

        $tab=Customer::select('id','name','address','phone','email','last_invoice_no','created_at')
                     ->where('store_id',$this->sdc->storeID())->orderBy('id','DESC')
                     ->when($search, function ($query) use ($search) {
                        $query->where('id','LIKE','%'.$search.'%');
                        $query->orWhere('name','LIKE','%'.$search.'%');
                        $query->orWhere('address','LIKE','%'.$search.'%');
                        $query->orWhere('phone','LIKE','%'.$search.'%');
                        $query->orWhere('email','LIKE','%'.$search.'%');
                        $query->orWhere('last_invoice_no','LIKE','%'.$search.'%');
                        $query->orWhere('created_at','LIKE','%'.$search.'%');

                        return $query;
                     })

                     ->count();
        return $tab;
    }

    private function methodToGetMembers($start, $length,$search=''){

        $tab=Customer::select('id','name','address','phone','email','last_invoice_no','created_at')
                     ->where('store_id',$this->sdc->storeID())->orderBy('id','DESC')
                     ->when($search, function ($query) use ($search) {
                        $query->where('id','LIKE','%'.$search.'%');
                        $query->orWhere('name','LIKE','%'.$search.'%');
                        $query->orWhere('address','LIKE','%'.$search.'%');
                        $query->orWhere('phone','LIKE','%'.$search.'%');
                        $query->orWhere('email','LIKE','%'.$search.'%');
                        $query->orWhere('last_invoice_no','LIKE','%'.$search.'%');
                        $query->orWhere('created_at','LIKE','%'.$search.'%');

                        return $query;
                     })
                     ->skip($start)->take($length)->get();
        return $tab;
    }


    public function datajson(Request $request){

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search');

        $search = (isset($search['value']))? $search['value'] : '';

        $total_members = $this->methodToGetMembersCount($search); // get your total no of data;
        $members = $this->methodToGetMembers($start, $length,$search); //supply start and length of the table data

        $data = array(
            'draw' => $draw,
            'recordsTotal' => $total_members,
            'recordsFiltered' => $total_members,
            'data' => $members,
        );

        echo json_encode($data);

    }

    public function show(Customer $customer)
    {


        

        //$tab=$customer::where('store_id',$this->sdc->storeID())->orderBy('id','DESC')->take(100)->get();
        //return view('apps.pages.customer.list',['dataTable'=>$tab]);
        return view('apps.pages.customer.list');
    }

    public function showCustomerDataTable(){
        //echo $this->ssp->test(); die();
        $dbDetails = array(
            'host' => env('DB_HOST', '127.0.0.1'),
            'user' => env('DB_USERNAME', '127.0.0.1'),
            'pass' => env('DB_PASSWORD', '127.0.0.1'),
            'db'   => env('DB_DATABASE', '127.0.0.1')
        );

        //dd($dbDetails);

        // DB table to use
        $table = 'lsp_customers';

        // Table's primary key
        $primaryKey = 'id';

        // Array of database columns which should be read and sent back to DataTables.
        // The `db` parameter represents the column name in the database. 
        // The `dt` parameter represents the DataTables column identifier.
        $columns = array(
            array( 'db' => 'name', 'dt' => 0 ),
            array( 'db' => 'address',  'dt' => 1 ),
            array( 'db' => 'email',      'dt' => 2 ),
            array( 'db' => 'phone',     'dt' => 3 ),
            array( 'db' => 'last_invoice_no',    'dt' => 4 ),
            array(
                'db'        => 'created_at',
                'dt'        => 5,
                'formatter' => function( $d, $row ) {
                    return date( 'jS M Y', strtotime($d));
                }
            ),
            array(
                'db'        => 'store_id',
                'dt'        => 6,
                'formatter' => function( $d, $row ) {
                    return ($d == 1)?'Active':'Inactive';
                }
            )
        );

        // Include SQL query processing class

        // Output data as json format
        echo json_encode(
            $this->ssp->simple( $_GET, $dbDetails, $table, $primaryKey, $columns )
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function edit(Customer $customer,$id=0)
    {
        $tab=$customer::find($id);
        $tabData=$customer::where('store_id',$this->sdc->storeID())->get();
        return view('apps.pages.customer.customer',['dataRow'=>$tab,'dataTable'=>$tabData,'edit'=>true]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Customer $customer,$id=0)
    {

        $this->validate($request,[
            'address'=>'required',
            'phone'=>'required',
        ]);

        $tab=$customer::find($id);
        $tab->name=$request->name;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->updated_by=$this->sdc->UserID();
        $tab->save();
        $this->sdc->log("customer","Customer account updated.");
        return redirect('customer')->with('status', $this->moduleName.' Updated Successfully !');

    }

   

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Customer $customer,$id=0)
    {
        $tab=$customer::find($id);
        $invoice_date=date('Y-m-d',strtotime($tab->created_at));
        $Todaydate=date('Y-m-d');
        if((RetailPosSummaryDateWise::where('report_date',$Todaydate)->count()==1) && ($invoice_date==$Todaydate))
        {
            RetailPosSummaryDateWise::where('report_date',$Todaydate)
            ->update([
               'customer_quantity' => \DB::raw('customer_quantity - 1')
            ]);
        }
        RetailPosSummary::where('id',1)->update(['customer_quantity' => \DB::raw('customer_quantity - 1')]);
        $tab->delete();
        

        $this->sdc->log("customer","Customer account deleted.");

        return redirect('customer/list')->with('status', $this->moduleName.' Deleted Successfully !');
    }

    public function importCustomer(){
        return view('apps.pages.customer.import');
    }
    
    public function importCustomerSave(request $request){
        
        $filename="";
        if (!empty($request->file('importfile'))) {
            $img = $request->file('importfile');
            $upload = 'upload/customer_import';
            //$filename=$img->getClientOriginalName();
            $filename = time() . "." . $img->getClientOriginalExtension();
            $success = $img->move($upload, $filename);
            $rows = Excel::load($upload.'/'. $filename)->get();
            $count_insert=0;
            if(isset($rows))
            {
                foreach ($rows as $key => $row) 
                {
                    $tab=new Customer;
                    $tab->name=$row->name;
                    $tab->address=$row->address;
                    $tab->phone=$row->phone;
                    $tab->email=$row->e_mail;
                    $tab->store_id=$this->sdc->storeID();
                    $tab->created_by=$this->sdc->UserID();
                    $tab->save();
                    $count_insert+=1;
                }
            }

            if($count_insert>0)
            {
                return redirect('customer/import')->with('status', $this->moduleName.' all data ('.$count_insert.') Added Successfully !');
            }
            else
            {
                return redirect('customer/import')->with('error', $this->moduleName.' no record inserted !');
            }
        }
        else
        {

         return redirect('customer/import')->with('error', $this->moduleName.' failed to upload !');
        }
    }
    public function customerReport(request $request, $id=0){
        $tab=customer::find($id);
        $tabData=invoice::join('customers','invoices.customer_id','=','customers.id')
                     ->select('invoices.*','customers.name as customer_name')
                     ->where('invoices.customer_id',$id)
                     ->get();
        return view('apps.pages.customer.report',['dataCus'=>$tab,'dataTable'=>$tabData]);
        
    }
}
