@extends('apps.layout.master')
@section('title','View Repair Detail - R'.$data->id)
@section('content')
<section id="file-exporaat">


		<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="basic-layout-card-center">
						<i class="icon-random"></i> Repair Info - R{{$data->id}} <code style="float: right;">Double click on field to edit value.</code>
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
						
								<input type="hidden" name="repair_id" id="repair_id" value="{{$data->id}}">

								<div class="row">
									<div class="col-md-4">
										<div class="form-body">
				                    		<h4 class="form-section"><i class="icon-cogs2"></i> Repair Info</h4>
				                    		<hr>
				                    		

				                    		<div class="row" style="margin-bottom: 15px;  margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Device</b></span>
															<input readonly="readonly"  style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->device_name}}">
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Model</b></span>
															<input readonly="readonly"  style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->model_name}}">
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Problem</b></span>
															<input readonly="readonly"  style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->problem_name}}">
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Repair Price</b></span>
															<input readonly="readonly"  style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->price}}">
														</div>
													</fieldset>
						                        </div>
				                    		</div> 

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="@if($data->payment_status=="Pending") width: 210px; @else width: 120px; @endif text-align: left; background: white;" id="basic-addon11"><b>Payment Status @if($data->payment_status=="Pending") - (Pending) @endif</b></span>
															@if($data->payment_status=="Pending")
																<a class="btn btn-green btn-block" style="border-top-left-radius: 0px; border-bottom-left-radius: 0px;" href="{{url('pos/repair/'.$data->id)}}"> 
																	<i class="icon-dollar"></i>{{$data->price}} to POS
																</a>
															@else
																<input readonly="readonly"  style="background: white;" name="email" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->payment_status}}">
															@endif
														</div>
													</fieldset>
						                        </div>
				                    		</div>	


										</div>
									</div>
									<div class="col-md-4">
										<div class="form-body">
				                    		<h4 class="form-section"><i class="icon-bar-graph-2"></i> Progress</h4>
				                    		<hr>
				                    		<div class="row" style="margin-bottom: 15px; margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left;  background: white;" id="basic-addon11"><b>Diagnostic</b></span>
															<input readonly="readonly" name="diagnostic_name" style="background: white; " ondblclick="liveselectedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->diagnostic}}">
															<select name="diagnostic" class="form-control" style="display: none;">
																<option selected="selected" value="Yes">Yes</option>
														        <option value="Yes">No</option>
															</select>
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="liveSelectsave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px; margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left;  background: white;" id="basic-addon11"><b>Repair Status</b></span>
															<input readonly="readonly" name="repair_status_name" style="background: white; " ondblclick="liveselectedit(this);" type="text" class="form-control" placeholder="Repair Status" aria-describedby="button-addon6" value="{{$data->repair_status}}">
															<select name="repair_status" class="form-control" style="display: none;">
																<option selected="selected" value="Pending">Pending</option>
														        <option value="Complete">Complete</option>
															</select>
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="liveSelectsave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left;  background: white;" id="basic-addon11"><b>Warranty Length</b></span>
															<input readonly="readonly" name="warranty_length_name" style="background: white; " ondblclick="liveselectedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->warranty_length}} Days">
															<select name="warranty_length" class="form-control" style="display: none;">
																@for($i=90; $i>=0; $i--)
																	<option selected="selected" value="{{$i}}">{{$i}} Days</option>
																@endfor
															</select>
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="liveSelectsave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left;  background: white;" id="basic-addon11"><b>LCD Status</b></span>
															<input readonly="readonly" name="lcd_status_name" style="background: white; " ondblclick="liveselectedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->lcd_status}}">
															<select name="lcd_status" class="form-control" style="display: none;">
																<option selected="selected" value="Good">Good</option>
														        <option value="Bad">Bad</option>
															</select>
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="liveSelectsave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>	                    		
				                    		
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-body">
				                    		<h4 class="form-section"><i class="icon-head"></i> Customer</h4>
				                    		<hr>
				                    		<div class="row" style="margin-bottom: 15px; margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Name</b></span>
															<input readonly="readonly" name="customer_name" style="background: white;" ondblclick="liveedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->customer_name}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Address</b></span>
															<input readonly="readonly"  style="background: white;" ondblclick="liveedit(this);" name="address" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$customer->address}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Phone</b></span>
															<input readonly="readonly"  style="background: white;" ondblclick="liveedit(this);" name="phone" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$customer->phone}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 120px; text-align: left; background: white;" id="basic-addon11"><b>Email</b></span>
															<input readonly="readonly"  style="background: white;" ondblclick="liveedit(this);" name="email" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$customer->email}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>	
				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
				                    				<a  class="btn btn-green btn-block" href="{{url('repair/print/'.$data->id)}}"><i class="icon-printer"></i> Print Repair</a>
						                    	</div>
						                    </div>			                    		
										</div>
									</div>
								</div>

								<h4 style="margin-top: 20px;" class="form-section"><i class="icon-compress"></i> Device Additional Information | Custom Fields</h4>
								<hr>

								<div class="row">
									<div class="col-md-6">
										<div class="form-body">
				                    		<div class="row" style="margin-bottom: 15px; margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Password</b></span>
															<input readonly="readonly" name="password"  style="background: white;" ondblclick="liveedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->password}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>IMEI</b></span>
															<input readonly="readonly" name="imei"  ondblclick="liveedit(this);"    style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->imei}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Tested Before By</b></span>
															<input readonly="readonly"  ondblclick="liveedit(this);"  style="background: white;"  name="tested_before_by" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->tested_before_by}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Tested After By</b></span>
															<input readonly="readonly" name="tested_after_by"  ondblclick="liveedit(this);"  style="background: white;" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->tested_after_by}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Additional Info</b></span>
															<div class="col-md-12" style="padding-top: 10px;">
																<?php 
																$repairArray=array();
																$dd=json_decode($data->repair_json);
																if(count($dd)>0)
																{
																	$i=1;
																	foreach($dd as $key=>$val)
																	{
																		if($key!="_token")
																		{
																			array_push($repairArray,$key);
																			//echo $key." : ".$val."<br>";
																		}
																		
																	}
																}

																if(count($ticketAsset)>0)
																{
																	foreach($ticketAsset as $row)
																	{
																		$fid="repair_".strtolower(preg_replace('/[^a-zA-Z0-9]/', "",$row->name));
																		if(in_array($fid,$repairArray))
																		{
																			?>
																			<label for="projectinput2"><input readonly="readonly" type="checkbox" disabled="disabled" checked="checked" id="projectinput2" class="repair_checkbox" name="repair_{{strtolower(preg_replace('/[^a-zA-Z0-9]/', "",$row->name))}}" /> {{$row->name}} </label>
																			<div class="clearfix"></div>
																			<?php
																		}
																		else
																		{
																			?>
																			<label for="projectinput2"><input  disabled="disabled"  type="checkbox"  id="projectinput2" class="repair_checkbox" name="repair_{{strtolower(preg_replace('/[^a-zA-Z0-9]/', "",$row->name))}}" /> {{$row->name}} </label>
																			<div class="clearfix"></div>
																			<?php
																		}
																	}
																}


																?>
															</div>
														</div>
													</fieldset>
						                        </div>
				                    		</div>
			                    		
				                    		
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-body">
				                    		<div class="row" style="margin-bottom: 15px; margin-top: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon" style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Tech Notes</b></span>
															<input readonly="readonly"  style="background: white;" ondblclick="liveedit(this);" name="tech_notes" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->tech_notes}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>How did you hear about us ?</b></span>
															<input readonly="readonly"  style="background: white;" ondblclick="liveedit(this);" name="how_did_you_hear_about_us" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->how_did_you_hear_about_us}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>Start Time</b></span>
															<input readonly="readonly" name="start_time" style="background: white;" ondblclick="liveedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->start_time}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		<div class="row" style="margin-bottom: 15px;">
				                    			<div class="col-md-12">
					                    			<fieldset class="">
														<div class="input-group">
															<span class="input-group-addon"  style="width: 140px; text-align: left; background: white;" id="basic-addon11"><b>End Time</b></span>
															<input readonly="readonly" name="end_time" style="background: white;" ondblclick="liveedit(this);" type="text" class="form-control" placeholder="Button on right" aria-describedby="button-addon6" value="{{$data->end_time}}">
															<span class="input-group-btn" id="button-addon6" style="display: none;">
																<button onclick="livesave(this)" class="btn btn-primary bg-green border-green" type="button"><i class="icon-check"></i></button>
															</span>
														</div>
													</fieldset>
						                        </div>
				                    		</div>

				                    		

			                    		
										</div>
									</div>
								</div>
															
							
					</div>
				</div>
			</div>
		</div>
	</div>
<!-- Both borders end -->


</section>
@endsection

@include('apps.include.datatable',['selectTwo'=>1,'dateDrop'=>1,'view_repair'=>1])