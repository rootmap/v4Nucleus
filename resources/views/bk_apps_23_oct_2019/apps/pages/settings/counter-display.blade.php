@extends('apps.layout.master')
@section('title','Counter Display Information')
@section('content')
<section id="file-exporaat">
<?php 
    $dataMenuAssigned=array();
    $dataMenuAssigned=StaticDataController::dataMenuAssigned();
    $userguideInit=StaticDataController::userguideInit();
    //dd($dataMenuAssigned);
?>

		<div class="row">
		<div class="col-md-6 offset-md-3" @if($userguideInit==1) data-step="1" data-intro="You can add/edit the new counter display information." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center">
						@if(isset($edit))
						<i class="icon-android-desktop"></i> Edit Counter Display Info
						@else
						<i class="icon-android-desktop"></i> Add New Counter Display Info
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
							action="{{url('counter/display/add/modify/'.$dataRow->id)}}"
						@else
							action="{{url('counter/display/add/save')}}"
						@endif
						>
							<div class="form-body">
								{{ csrf_field() }}
								<div class="form-group">
									<label for="eventRegInput1">Counter IP [{{$_SERVER['REMOTE_ADDR']}}] <span class="text-danger">*</span></label>
									<input type="text" 
									@if(isset($edit))
										value="{{$dataRow->user_pc_ip}}" 
									@endif 
									 id="eventRegInput1" class="form-control border-green" placeholder="Counter IP Address" name="user_pc_ip">
								</div>	

								<div class="form-group">
									<label for="eventRegInput1">Counter Name <span class="text-danger">*</span></label>
									<input type="text" 
									@if(isset($edit))
										value="{{$dataRow->user_pc_name}}" 
									@endif 
									 id="eventRegInput1" class="form-control border-green" placeholder="Counter Name" name="user_pc_name">
								</div>							
							</div>

							<div class="form-actions center">
								<button type="button" class="btn btn-green btn-lighten-2 mr-1" @if($userguideInit==1) data-step="3" data-intro="if you want clear all information then click the cancel button." @endif>
									<i class="icon-cross2"></i> Cancel
								</button>
								@if(isset($edit))
								<button type="submit" class="btn btn-green btn-darken-2">
									<i class="icon-check2"></i> Update
								</button>
								@else
								<button type="submit" class="btn btn-green btn-darken-2" @if($userguideInit==1) data-step="2" data-intro="When you fill up all information then click save button." @endif>
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
	<div class="col-xs-12" @if($userguideInit==1) data-step="4" data-intro="You are seeing your all counter display info list." @endif>
		<div class="card">
			<div class="card-header">
				<h4 class="card-title"><i class="icon-android-desktop"></i> Counter Display Info List</h4>
				<a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
        		<div class="heading-elements">
					<ul class="list-inline mb-0">
						<li><a data-action="collapse"><i class="icon-minus4"></i></a></li>
						<li><a data-action="expand"><i class="icon-expand2"></i></a></li>
					</ul>
				</div>
			</div>
			<div class="card-body collapse in">
				<div class="card-block card-dashboard">
					<table class="table table-striped table-bordered zero-configuration">
						<thead>
							<tr>
								<th>ID</th>
								<th>Counter IP Address</th>
								<th>Counter Name</th>
								<th style="width: 100px;">Action</th>
							</tr>
						</thead>
						<tbody>
							@if(isset($dataTable))
							@foreach($dataTable as $row)
							<tr>
								<td>{{$row->id}}</td>
								<td>{{$row->user_pc_ip}}</td>
								<td>{{$row->user_pc_name}}</td>
								<td>
                                        @if(in_array('Counter_Display_Info_List_Edit', $dataMenuAssigned)) 
                                        <a href="{{url('counter/display/add/edit/'.$row->id)}}" title="Edit" class="btn btn-sm btn-outline-green"><i class="icon-pencil22"></i></a>
                                        @endif
                                        @if(in_array('Counter_Display_Info_List_Delete', $dataMenuAssigned)) 
                                        <a  href="{{url('counter/display/add/delete/'.$row->id)}}" title="Delete" class="btn btn-sm btn-outline-green btn-darken-2"><i class="icon-cross"></i></a>
                                        @endif

                                </div>
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