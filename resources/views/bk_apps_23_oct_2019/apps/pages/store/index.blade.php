@extends('apps.layout.master')
@section('title','Store-Shop Setting')
@section('content')
<section id="file-exporaat">
<?php 
    $userguideInit=StaticDataController::userguideInit();
?>
		<div class="row">
		<div class="col-md-6 offset-md-3" @if($userguideInit==1) data-step="1" data-intro="In this section, you can added/modify new store shop." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center">
						
						<i class="icon-user-plus"></i> Add New Store-Shop
						
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
						<span id="pageMSG"></span>
						<form class="form" method="post" 
						@if(isset($edit)) 
							action="{{url('store-shop/modify/'.$edit->id)}}"
						@else
							action="{{url('store-shop/save')}}"
						@endif
						>
						{{ csrf_field() }}
							<div class="form-body">
							
		                        <div class="form-group">
	                            	<label for="projectinput1">Store Name </label>
		                            <input type="text" 
										@if(isset($edit))
											value="{{$edit->name}}" 
										@endif 
										 id="eventRegInput1" class="form-control border-green" placeholder="Full name" name="name">
		                        </div>
		                        <div class="form-group">
	                            	<label for="projectinput1">Contact Email </label>
		                            	 
										
										<input type="text" id="eventRegInput1" class="form-control border-green" placeholder="Email Address" name="email" @if(isset($edit)) value="{{$edit->email}}" @endif>
											
										
										
		                        </div>
		                        <div class="form-group">
	                            	<label for="projectinput1">Phone </label>
		                            	<input type="text" 
										@if(isset($edit))
											value="{{$edit->phone}}" 
										@endif 
										 id="eventRegInput1" class="form-control border-green" placeholder="Phone Number" name="phone">
		                        </div>
		                        <div class="form-group">
	                            	<label for="projectinput1">Address </label>
		                            	<input type="text" 
										@if(isset($edit))
											value="{{$edit->address}}" 
										@endif 
										 id="eventRegInput1" class="form-control border-green" placeholder="Address" name="address">
		                        </div>

		                        <div class="form-group">
	                            	<label for="projectinput1">Store ID </label>
		                            	<input type="text" 
										@if(isset($edit))
											value="{{$edit->store_id}}" 
										@else
											 value="{{$store_id}}" 
										@endif 
										 id="eventRegInput1" readonly="readonly" class="form-control border-green" placeholder="Store ID" name="store_id">
		                        </div>
							<div class="form-actions center">
								<button type="reset" class="btn btn-green btn-lighten-2 mr-1" @if($userguideInit==1) data-step="3" data-intro="if you want clear all information then click the cancel button." @endif>
									<i class="icon-cross2"></i> Cancel
								</button>
								
								<button type="submit" class="btn btn-green btn-darken-2" @if($userguideInit==1) data-step="2" data-intro="When you fill up all information then click save button." @endif>
									<i class="icon-check2"></i> Save
								</button>
							
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>
<!-- Both borders end-->

<!-- Both borders end -->
</section>
@endsection

@section('css')
<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/forms/selects/select2.min.css')}}">
<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/css/pages/invoice.min.css')}}">
@endsection

@section('js')
<script src="{{url('theme/app-assets/vendors/js/forms/select/select2.full.min.js')}}" type="text/javascript"></script>
<script src="{{url('theme/app-assets/js/scripts/forms/select/form-select2.min.js')}}" type="text/javascript"></script>
@endsection