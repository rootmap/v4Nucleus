@extends('apps.layout.master')
@section('title','Product')
@section('content')
<section id="form-action-layouts">
    <?php 
        $userguideInit=StaticDataController::userguideInit();
        //dd($dataMenuAssigned);
    ?>
<div class="row">
    <div class="col-md-12" @if($userguideInit==1) data-step="1" data-intro="You can see product by date wise and generate excel or PDF." @endif>
        <div class="card">
            <div class="card-header">
                <h4 class="card-title" id="basic-layout-card-center"><i class="icon-filter_list"></i> Product Report Filter</h4>
                <a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="icon-minus4"></i></a></li>
                        <li><a data-action="expand"><i class="icon-expand2"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body collapse in">
                <div class="card-block">
                    <form method="post" action="{{url('product/report')}}">
                        {{csrf_field()}}
                        <fieldset class="form-group">
                            <div class="row">
                                <div class="col-md-3">
                                    <h4>Start Date</h4>
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="icon-calendar3"></i></span>
                                        <input 
                                        @if(!empty($start_date))
                                            value="{{$start_date}}"  
                                        @endif
                                        name="start_date" type="text" class="form-control DropDateWithformat" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <h4>End Date</h4>
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="icon-calendar3"></i></span>
                                        <input 
                                        @if(!empty($end_date))
                                            value="{{$end_date}}"  
                                        @endif 
                                         name="end_date" type="text" class="form-control DropDateWithformat" />
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    
                                    <div class="input-group" style="margin-top:32px;">
                                        <button type="submit" class="btn btn-green btn-darken-1 mr-1" @if($userguideInit==1) data-step="2" data-intro="If you click this button then it will generate your report." @endif>
                                            <i class="icon-check2"></i> Generate Report
                                        </button>
                                        <a href="javascript:void(0);" data-url="{{url('product/excel/report')}}" class="btn btn-green btn-darken-2 mr-1 change-action"  @if($userguideInit==1) data-step="3" data-intro="If you click this button then it will generate excel file." @endif>
                                            <i class="icon-file-excel-o"></i> Generate Excel
                                        </a>
                                        <a href="javascript:void(0);" data-url="{{url('product/pdf/report')}}" class="btn btn-green btn-darken-3 mr-1 change-action" @if($userguideInit==1) data-step="4" data-intro="If you click this button then it will generate pdf file." @endif>
                                            <i class="icon-file-pdf-o"></i> Generate PDF
                                        </a>
                                        <a href="{{url('product/report')}}" style="margin-left: 5px;" class="btn btn-green btn-darken-4" @if($userguideInit==1) data-step="5" data-intro="if you want clear all information then click the reset button." @endif>
                                            <i class="icon-refresh"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
  
    <!-- Both borders end-->
<div class="row">
    <div class="col-xs-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="icon-database"></i> Product List</h4>
                <a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a href="{{url('product/excel/report')}}"><i class="icon-file-excel" style="font-size: 20px;"></i></a></li>
                        <li><a href="{{url('product/pdf/report')}}"><i class="icon-file-pdf"  style="font-size: 20px;"></i></a></li>
                        <li><a data-action="collapse"><i class="icon-minus4"></i></a></li>
                        <li><a data-action="expand"><i class="icon-expand2"></i></a></li>
                    </ul>
                </div>
            </div>

                <div class="card-body collapse in">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="report_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th width="450">Name</th>
                                <th style="width: 50px;">Quantity</th>
                                <th>Price</th>
                                <th>Cost</th>
                                <th>Product Added</th>
                                <th>Total price</th>
                                <th>Total cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(isset($dataTable))
                            @foreach($dataTable as $row)
                            <tr>
                                <td>{{$row->id}}</td>
                                <td>{{$row->name}}</td>
                                <td>{{$row->quantity}}</td>
                                <td>{{$row->price}}</td>
                                <td>{{$row->cost}}</td>
                                <td>{{formatDateTime($row->created_at)}}</td>
                                <td>{{($row->price*$row->quantity)}}</td>
                                <td>{{($row->cost*$row->quantity)}}</td>
                            </tr>
                            @endforeach
                            @else
                            <tr>
                                <td colspan="6">No Record Found</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Both borders end -->

</section>

@endsection



@include('apps.include.datatablecssjs',['selectTwo'=>1,'dateDrop'=>1])
@section('RoleWiseMenujs')
   <script>
    
    $(document).ready(function(e){

        var dataObj="";
        function replaceNull(valH){
            var returnHt='';
            if(valH !== null && valH !== '') {
                    returnHt=valH;
            }
            return returnHt;
        }

        @if(!empty($start_date) || !empty($end_date))
            @if(isset($dataTable))
                @if(count($dataTable)>0)
                    $('#report_table').DataTable();
                @endif
            @endif
        @else

        $('#report_table').dataTable({
            "bProcessing": true,
            "serverSide": true,
            "ajax":{
                url :"{{url('product/data/table/report/json')}}",
                headers: {
                    'X-CSRF-TOKEN':'{{csrf_token()}}',
                },
                type: "POST",
                complete:function(data){
                    console.log(data.responseJSON);
                    var totalData=data.responseJSON;
                    console.log(totalData.data);
                    var strHTML='';
                    var totalPrice=0;
                    $.each(totalData.data,function(key,row){
                        console.log(row);

                        var totalPrPrice=row.quantity*row.price;
                        var totalPrCost=row.quantity*row.cost;

                        strHTML+='<tr>';
                        strHTML+='      <td>'+row.id+'</td>';
                        strHTML+='      <td>'+replaceNull(row.name)+'</td>';
                        strHTML+='      <td>'+replaceNull(row.quantity)+'</td>';
                        strHTML+='      <td>'+replaceNull(row.price)+'</td>';
                        strHTML+='      <td>'+replaceNull(row.cost)+'</td>';
                        strHTML+='      <td>'+formatDate(replaceNull(row.created_at))+'</td>';
                        strHTML+='      <td>'+number_format(replaceNull(totalPrPrice))+'</td>';
                        strHTML+='      <td>'+number_format(replaceNull(totalPrCost))+'</td>';                    
                        strHTML+='</tr>';

                       // totalPrice+=replaceNull(row.paid_amount)-0;

                    });

                    $("tbody").html(strHTML);
                    $('#report_table').DataTable();
                },
                initComplete: function(settings, json) {
                    alert( 'DataTables has finished its initialisation.' );
                  },
                error: function(){
                  $("#report_table_processing").css("display","none");
                }
            }
        });

        @endif
    });


    </script>

@endsection