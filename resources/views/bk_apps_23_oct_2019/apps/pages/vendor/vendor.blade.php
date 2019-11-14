@extends('apps.layout.master')
@section('title','Vendor')
@section('content')
<section id="form-action-layouts">
<?php 
    $userguideInit=StaticDataController::userguideInit();
    //dd($dataMenuAssigned);
?>

		<div class="row">
		<div class="col-md-8 offset-md-2" @if($userguideInit==1) data-step="1" data-intro="In this section, you can add a new vendor." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center">
                        
                        @if(isset($dataRow))
                        <i class="icon-clipboard2"></i> Edit Vendor
                        @else
                        <i class="icon-clipboard2"></i> Add Vendor
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
                        @if(isset($dataRow))
                            action="{{url('vendor/modify/'.$dataRow->id)}}"
                        @else
                            action="{{url('vendor/save')}}"
                        @endif
                        >
							<div class="form-body">
                                {{ csrf_field() }}
							

								<div class="row">
									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegName">Name<span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->name}}" 
                                        @endif 
                                        type="text" id="name" class="form-control border-green" placeholder="Name"  name="name">
									</div>	

									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegEmail">Email <span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->email}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="Email"  name="email">
									</div>
								
									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegInputPhone">Phone <span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->phone}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="Phone Number" name="phone">
									</div>


									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegAddress">Address <span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->address}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="Address"  name="address">
									</div>


								</div>


								<div class="row">
									<div class="form-group col-md-6 mb-2">
										<label for="vedorRegcity">City<span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->city}}" 
                                        @endif 
                                        type="text" id="city" class="form-control border-green" placeholder="City" name="city">
									</div>	

									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegInputStare">State/Country <span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->state}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="State/Country "  name="state">
									</div>
								
									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegInputZip">Zip/Postal Code<span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->zip}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="Zip/Postal Code"  name="zip">
									</div>


									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegInputWebsite">Website<span class="text-danger">*</span></label>
										<input 
                                        @if(isset($dataRow))
                                        value="{{$dataRow->website}}" 
                                        @endif 
                                        type="text"  class="form-control border-green" placeholder="Website"  name="website">
									</div>


									<div class="form-group col-md-6 mb-2">
										<label for="vendorRegInputNotes">Notes<span class="text-danger">*</span></label>
										<textarea  class="form-control border-green" placeholder="Notes"  name="notes">@if(isset($dataRow)){{$dataRow->notes}}@endif 
                                        </textarea> 
									</div>

								
								</div>

							
							</div>

							<div class="form-actions center">
								<button type="button" class="btn btn-green btn-darken-2 mr-1" @if($userguideInit==1) data-step="3" data-intro="if you want clear all information then click the clear button." @endif>
									<i class="icon-cross2"></i> Cancel
								</button>
								@if(isset($dataRow))
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
	<!-- Both borders end--


</section>

@endsection

@include('apps.include.datatable',['JDataTable'=>1])
