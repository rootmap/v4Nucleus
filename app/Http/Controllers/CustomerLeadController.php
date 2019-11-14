<?php

namespace App\Http\Controllers;
use App\Customer;
use App\CustomerLead;
use Illuminate\Http\Request;

class CustomerLeadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    private $moduleName="Customer Lead Info ";
    private $sdc;
    public function __construct(){ $this->sdc = new StaticDataController(); }

    public function index()
    {
        $tab=Customer::where('store_id',$this->sdc->storeID())->get();
        return view('apps.pages.customer.customer-lead',['existing_cus'=>$tab]);
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
    public function store(Request $request)
    {
        $this->validate($request,[
            'customer_id'=>'required',
            'name'=>'required',
            'address'=>'required',
            'phone'=>'required',
            'email'=>'required',
            'lead_info'=>'required',
        ]);

        $customer_id=$request->customer_id;

        if($customer_id==0)
        {
            $tab=new Customer;
            $tab->name=$request->name;
            $tab->address=$request->address;
            $tab->phone=$request->phone;
            $tab->email=$request->email;
            $tab->store_id=$this->sdc->storeID();
            $tab->created_by=$this->sdc->UserID();
            $tab->save();
            $customer_id=$tab->id;
        }

        $tab=new CustomerLead; 
        $tab->customer_id=$customer_id;
        $tab->name=$request->name;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->lead_info=$request->lead_info;
        $tab->store_id=$this->sdc->storeID();
        $tab->created_by=$this->sdc->UserID();
        $tab->save(); 

        $this->sdc->log("customer-lead","New Customer Lead created.");
        return redirect('customer/lead/new')->with('status', $this->moduleName.' Added Successfully !');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\CustomerLead  $customerLead
     * @return \Illuminate\Http\Response
     */
    private function methodToGetMembersCount($search=''){

        $tab=CustomerLead::select('id','name','address','phone','email','lead_info','created_at')
                     ->where('store_id',$this->sdc->storeID())->orderBy('id','DESC')
                     ->when($search, function ($query) use ($search) {
                        $query->where('id','LIKE','%'.$search.'%');
                        $query->orWhere('name','LIKE','%'.$search.'%');
                        $query->orWhere('address','LIKE','%'.$search.'%');
                        $query->orWhere('phone','LIKE','%'.$search.'%');
                        $query->orWhere('email','LIKE','%'.$search.'%');
                        $query->orWhere('lead_info','LIKE','%'.$search.'%');
                        $query->orWhere('created_at','LIKE','%'.$search.'%');

                        return $query;
                     })

                     ->count();
        return $tab;
    }

    private function methodToGetMembers($start, $length,$search=''){

        $tab=CustomerLead::select('id','name','address','phone','email','lead_info','created_at')
                     ->where('store_id',$this->sdc->storeID())->orderBy('id','DESC')
                     ->when($search, function ($query) use ($search) {
                        $query->where('id','LIKE','%'.$search.'%');
                        $query->orWhere('name','LIKE','%'.$search.'%');
                        $query->orWhere('address','LIKE','%'.$search.'%');
                        $query->orWhere('phone','LIKE','%'.$search.'%');
                        $query->orWhere('email','LIKE','%'.$search.'%');
                        $query->orWhere('lead_info','LIKE','%'.$search.'%');
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



    public function show(CustomerLead $customerLead)
    {
        //$tab=$customerLead::where('store_id',$this->sdc->storeID())->orderBy('id','DESC')->take(100)->get();['dataTable'=>$tab]
        return view('apps.pages.customer.customer-lead-list');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CustomerLead  $customerLead
     * @return \Illuminate\Http\Response
     */
    public function edit(CustomerLead $customerLead,$id=0)
    {
        $tab=Customer::where('store_id',$this->sdc->storeID())->get();
        $dataRow=$customerLead::find($id);
       // dd($dataRow);
        return view('apps.pages.customer.customer-lead',['existing_cus'=>$tab,'dataRow'=>$dataRow,'edit'=>$id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CustomerLead  $customerLead
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CustomerLead $customerLead,$id=0)
    {
        $this->validate($request,[
            'customer_id'=>'required',
            'name'=>'required',
            'address'=>'required',
            'phone'=>'required',
            'email'=>'required',
            'lead_info'=>'required',
        ]);

        $tab=$customerLead::find($id);
        $tab->customer_id=$request->customer_id;
        $tab->name=$request->name;
        $tab->address=$request->address;
        $tab->phone=$request->phone;
        $tab->email=$request->email;
        $tab->lead_info=$request->lead_info;
        $tab->updated_by=$this->sdc->UserID();
        $tab->save();
        $this->sdc->log("customer-lead","Customer Lead account updated.");
        return redirect('customer/lead/list')->with('status', $this->moduleName.' Updated Successfully !');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\CustomerLead  $customerLead
     * @return \Illuminate\Http\Response
     */
    public function destroy(CustomerLead $customerLead,$id=0)
    {
        $tab=$customerLead::find($id);
        $tab->delete();
        $this->sdc->log("customer-lead","Customer Lead account deleted."); 
        return redirect('customer/lead/list')->with('status', $this->moduleName.' Deleted Successfully !');
    }
}
