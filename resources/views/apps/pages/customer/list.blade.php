@extends('apps.layout.master')
@section('title','Customer')
@section('content')
<section id="file-exporaat">
<?php 
    $dataMenuAssigned=array();
    $dataMenuAssigned=StaticDataController::dataMenuAssigned();
	$userguideInit=StaticDataController::userguideInit();
    //dd($dataMenuAssigned);
?>

	<!-- Both borders end-->
<div class="row">
	<div class="col-xs-12" @if($userguideInit==1) data-step="1" data-intro="You are seeing all customer in this table and see customer report." @endif>
		<div class="card">
			<div class="card-header">
				<h4 class="card-title"><i class="icon-users2"></i> Customer List</h4>
				<a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
        		<div class="heading-elements">
					<ul class="list-inline mb-0">
						<li><a href="{{url('customer/excel/report')}}"><i class="icon-file-excel" style="font-size: 20px;"></i></a></li>
                        <li><a href="{{url('customer/pdf/report')}}"><i class="icon-file-pdf"  style="font-size: 20px;"></i></a></li>
                        <li><a data-action="collapse"><i class="icon-minus4"></i></a></li>
						<li><a data-action="expand"><i class="icon-expand2"></i></a></li>
					</ul>
				</div>
			</div>
			<div class="card-body collapse in">
				<div class="table-responsive">
					<table class="table table-striped table-bordered zero-configuration">
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Address</th>
								<th>Phone</th>
								<th>Email</th>
								<th>Invoice ID</th>
								@if(in_array('list_customer_report', $dataMenuAssigned))
									<th>Report</th>
								@endif
							</tr>
						</thead>
						<tbody>
							@if(isset($dataTable))
							@foreach($dataTable as $row)
							<tr>
								<td>{{$row->id}}</td>
								<td>{{$row->name}}</td>
								<td>{{$row->address}}</td>
								<td>{{$row->phone}}</td>
								<td>{{$row->email}}</td>
								<td>{{$row->last_invoice_no}}</td>
								@if(in_array('list_customer_report', $dataMenuAssigned))
								<td width="450">
									<a href="{{url('customer/report/'.$row->id)}}" class="btn btn-green mr-1 change-action" @if($userguideInit==1) data-step="2" data-intro="When you see report then click view report button." @endif>
										<i class="icon-file-o"></i> View Report
									</a>
									<a href="{{url('customer/edit/'.$row->id)}}" class="btn btn-green mr-1 btn-darken-2" @if($userguideInit==1) data-step="3" data-intro="When you see modify then click edit button." @endif>
										<i class="icon-edit"></i> edit
									</a>
									<a href="{{url('customer/delete/'.$row->id)}}" class="btn btn-green mr-1 btn-darken-3" @if($userguideInit==1) data-step="4" data-intro="When you delete report then click delete button." @endif>
										<i class="icon-trash"></i> Delete
									</a>
								</td>
								@endif
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

@include('apps.include.datatable',['JDataTable'=>1])