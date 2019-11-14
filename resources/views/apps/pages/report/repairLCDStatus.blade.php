@extends('apps.layout.master')
@section('title','Repair LCD Report')
@section('content')
<section id="form-action-layouts">
	<?php
	$userguideInit=StaticDataController::userguideInit();
	?>
		<div class="row">
		<div class="col-md-12" @if($userguideInit==1) data-step="1" data-intro="You can see Repair LCD by date wise or invoice or Customer or LCD Status and generate excel or PDF." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center"><i class="icon-filter_list"></i> Repair LCD Report Filter</h4>
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
						<form method="post" action="{{url('lcd/status/report')}}">
							{{csrf_field()}}
							<fieldset class="form-group">
	                            <div class="row">
	                                <div class="col-md-2">
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
	                                <div class="col-md-2">
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
	                                <div class="col-md-2">
	                                    <h4>Invoice ID</h4>
	                                    <div class="input-group">
										<input 
										 @if(!empty($invoice_id))
	                                        	value="{{$invoice_id}}"  
	                                     @endif 
										 type="text" id="eventRegInput1" class="form-control border-green" placeholder="Invoice ID" name="invoice_id">
	                                    </div>
	                                </div>
	                                <div class="col-md-3">
	                                    <h4>Customer</h4>
	                                    <div class="input-group">
											<select name="customer_id" class="select2 form-control">
												<option value="">Select a customer</option>
												@if(isset($customer))
													@foreach($customer as $cus)
													<option 
													 @if(!empty($customer_id) && $customer_id==$cus->id)
				                                        selected="selected"  
				                                     @endif 
													value="{{$cus->id}}">{{$cus->name}}</option>
													@endforeach
												@endif
											</select>
	                                    </div>
	                                </div>
	                                <div class="col-md-3">
	                                    <h4>LCD Status</h4>
	                                    <div class="input-group">
											<select name="lcd_status" class="select2 form-control">
												<option value="">Select a Status</option>
													<option 
													 @if(!empty($lcd_status) && $lcd_status=="Good")
				                                        selected="selected"  
				                                     @endif 
													 value="Good">Good</option>
													<option 
													 @if(!empty($lcd_status) && $lcd_status=="Bad")
				                                        selected="selected"  
				                                     @endif  
													value="Bad">Bad</option>
											</select>
	                                    </div>
	                                </div>
	                                <div class="col-md-12">
	                                    
	                                    <div class="input-group" style="margin-top:32px;">
	                                        <button type="submit" class="btn btn-green btn-darken-1 mr-1" @if($userguideInit==1) data-step="2" data-intro="If you click this button then it will generate your report." @endif>
												<i class="icon-check2"></i> Generate
											</button>
											<a href="javascript:void(0);" data-url="{{url('lcd/status/excel/report')}}" class="btn btn-green btn-darken-2 mr-1 change-action" @if($userguideInit==1) data-step="3" data-intro="If you click this button then it will generate excel file." @endif>
												<i class="icon-file-excel-o"></i> Generate Excel
											</a>
											<a href="javascript:void(0);" data-url="{{url('lcd/status/pdf/report')}}" class="btn btn-green btn-darken-3 mr-1 change-action" @if($userguideInit==1) data-step="4" data-intro="If you click this button then it will generate pdf file." @endif>
												<i class="icon-file-pdf-o"></i> Generate PDF
											</a>
											<a href="{{url('lcd/status/report')}}" style="margin-left: 5px;" class="btn btn-green btn-darken-4" @if($userguideInit==1) data-step="5" data-intro="if you want clear all information then click the reset button." @endif>
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
				<h4 class="card-title"><i class="icon-clear_all"></i> Repair LCD Status List</h4>
				<a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
        		<div class="heading-elements">
					<ul class="list-inline mb-0">
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
								<th>Id</th>
								<th>Date</th>
								<th width="250">Customer</th>
								<th>Repair Detail</th>
								<th>Price</th>
								<th>LCD Status</th>
								<th>Status</th>
								<th>Invoice ID</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							$invoice_total=0;
							$cost_total=0;
							$paid_amount=0;
							$good_amount=0;
							$bad_amount=0;
							?>
							@if(isset($invoice))
								@foreach($invoice as $inv)
								<tr>
	                                <td>{{$inv->id}}</td>
	                                <td>{{formatDate($inv->created_at)}}</td>
	                                <td>{{$inv->customer_name}}</td>
	                                <td>{{$inv->product_name}}</td>
	                                <td>{{number_format($inv->price,2)}}</td>
	                                <td>{{$inv->lcd_status}}</td>
	                                <td>{{$inv->payment_status}}</td>
	                                <td>{{$inv->invoice_id}}</td>
	                            </tr>
	                            <?php 
								$paid_amount+=$inv->price;

								if($inv->lcd_status=="Good")
								{
									$good_amount+=1;
								}
								
								if($inv->lcd_status=="Bad")
								{
									$bad_amount+=1;
								}

								?>
	                            @endforeach
							@endif

						</tbody>
					</table>
				</div>
			</div>
		</div>




						<div class="col-lg-4 col-sm-4 border-right-blue bg-blue border-right-lighten-4">
                            <div class="card-block text-xs-center">
                                <h1 class="display-4 white"><i class="icon-money font-large-2"></i> $<span id="totalPaidAmount">{{$paid_amount}}</span></h1>
                                <span class="white">Total Paid Amount</span>
                            </div>
                        </div>

                        <div class="col-lg-4 col-sm-4 border-right-blue bg-green bg-darken-2 border-right-lighten-4">
                            <div class="card-block text-xs-center">
                                <h1 class="display-4 white"><span id="totalGoodLCD">{{$good_amount}}</span></h1>
                                <span class="white">Good LCD</span>
                            </div>
                        </div>

                        <div class="col-lg-4 col-sm-4 border-right-blue bg-green  bg-lighten-2 border-right-lighten-4">
                            <div class="card-block text-xs-center">
                                <h1 class="display-4 white"><span id="totalBadLCD">{{$bad_amount}}</span></h1>
                                <span class="white">Bad LCD</span>
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

		@if(!empty($start_date) || !empty($end_date) || !empty($invoice_id) || !empty($customer_id) || !empty($lcd_status))
			@if(isset($invoice))
        		@if(count($invoice)>0)
        			$('#report_table').DataTable();
        		@endif
        	@endif
        @else

		$('#report_table').dataTable({
			"bProcessing": true,
         	"serverSide": true,
         	"ajax":{
	            url :"{{url('lcd/status/data/report/json')}}",
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
	            	var totalGood=0;
	            	var totalBad=0;
	            	$.each(totalData.data,function(key,row){
	            		console.log(row);
	            		strHTML+='<tr>';
						strHTML+='		<td>'+row.id+'</td>';
						strHTML+='		<td>'+formatDate(replaceNull(row.created_at))+'</td>';
						strHTML+='		<td>'+replaceNull(row.customer_name)+'</td>';
						strHTML+='		<td>'+replaceNull(row.product_name)+'</td>';
						strHTML+='		<td>'+number_format(replaceNull(row.price))+'</td>';
						strHTML+='		<td>'+replaceNull(row.lcd_status)+'</td>';
						strHTML+='		<td>'+replaceNull(row.payment_status)+'</td>';
						strHTML+='		<td>'+replaceNull(row.invoice_id)+'</td>';						
						strHTML+='</tr>';

						if(row.lcd_status=="Good")
						{
							totalGood+=1-0;
						}
						
						if(row.lcd_status=="Bad")
						{
							totalBad+=1-0;
						}

						totalPrice+=number_format(replaceNull(row.price))-0;

	            	});

	            	$("#totalPaidAmount").html(number_format(totalPrice));
	            	$("#totalGoodLCD").html(number_format(totalGood));
	            	$("#totalBadLCD").html(number_format(totalBad));

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