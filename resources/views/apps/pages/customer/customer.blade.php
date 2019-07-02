@extends('apps.layout.master')
@section('title','Customer Info')
@section('content')
<section id="file-exporaat">
	<?php 
	    $userguideInit=StaticDataController::userguideInit();

	    //dd($dataMenuAssigned);
	?>
		<div class="row">
		<div class="col-md-6 offset-md-3" @if($userguideInit==1) data-step="1" data-intro="In this section, you can add a new customer." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center">
						@if(isset($edit))
						<i class="icon-user-plus"></i> Edit Customer
						@else
						<i class="icon-user-plus"></i> Add Customer
						@endif
					</h4>
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
						<form class="form" method="post" 
						@if(isset($edit))
							action="{{url('customer/modify/'.$dataRow->id)}}"
						@else
							action="{{url('customer/save')}}"
						@endif
						>
							<div class="form-body">
								{{ csrf_field() }}
								<div class="form-group">
									<label for="eventRegInput1">Name <span class="text-danger">*</span></label>
									<input type="text" 
									@if(isset($edit))
										value="{{$dataRow->name}}" 
									@endif 
									 id="eventRegInput1" class="form-control border-green" placeholder="Customer Name" name="name">
								</div>


									<div class="form-group">
										<label for="eventRegInput2">Address <span class="text-danger">*</span></label>
										<input type="text" id="text" class="form-control border-green" 
										@if(isset($edit))
										value="{{$dataRow->address}}" 
										@endif 
										placeholder="address" name="address">
									</div>	

									<div class="form-group">
										<label for="eventRegInput3">Phone <span class="text-danger">*</span></label>
										<input type="tel" 
										@if(isset($edit))
										value="{{$dataRow->phone}}" 
										@endif 
										id="tel" class="form-control border-green" placeholder="1-(555)-555-5555" name="phone">
									</div>
								
									<div class="form-group">
										<label for="eventRegInput3">E-mail <span class="text-danger">*</span></label>
										<input type="email" 
										@if(isset($edit))
										value="{{$dataRow->email}}" 
										@endif 										
										id="eventRegInput4" class="form-control border-green" placeholder="Email Address" name="email">
									</div>

							
							</div>

							<div class="form-actions center">
								<button type="button" class="btn btn-green btn-darken-2 mr-1" @if($userguideInit==1) data-step="3" data-intro="if you want clear all information then click the clear button." @endif>
									<i class="icon-cross2"></i> Cancel
								</button>
								@if(isset($edit))
								<button type="submit" class="btn btn-green btn-accent-2">
									<i class="icon-check2"></i> Update
								</button>
								@else
								<button type="submit" class="btn btn-green btn-accent-2" @if($userguideInit==1) data-step="2" data-intro="When you fill up all information then click save button." @endif>
									<i class="icon-check2"></i> Save
								</button>
								@endif
							</div>
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
				<h4 class="card-title"><i class="icon-users2"></i> Customer List</h4>
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
					<table class="table table-striped table-bordered zero-configuration">
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Address</th>
								<th>Phone</th>
								<th>Email</th>
								<th style="width: 100px;">Action</th>
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
								<td>
                                        <a href="{{url('customer/edit/'.$row->id)}}" title="Edit" class="btn btn-sm btn-outline-green"><i class="icon-pencil22"></i></a>
                                        <a  href="{{url('customer/delete/'.$row->id)}}" title="Delete" class="btn btn-sm btn-outline-green btn-darken-1"><i class="icon-cross"></i></a>
								</td>
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