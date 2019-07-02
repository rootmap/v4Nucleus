@extends('apps.layout.master')
@section('title','Calculate Variance')
@section('content')
<section id="form-action-layouts">
	<div class="row match-height">
<?php 
	 $userguideInit=StaticDataController::userguideInit();
?>

		<div class="col-md-12" @if($userguideInit==1) data-step="1" data-intro="In this section, you can add a quantity in hand for each item." @endif>
			<div class="card">
				<div class="card-header">
					<h4 class="card-title" id="from-actions-bottom-right"><i class="icon-tab"></i> Enter Quantity in hand for each item</h4>
					<a class="heading-elements-toggle"><i class="icon-ellipsis font-medium-3"></i></a>
					<div class="heading-elements">
						<ul class="list-inline mb-0">
							<li><a data-action="expand"><i class="icon-expand2"></i></a></li>
						</ul>
					</div>
				</div>
				<div class="card-body collapse in">
					<div class="card-block">
						<form method="post" class="form form-horizontal striped-labels form-bordered" action="{{url('variance/save')}}">
	                    	<div class="form-body">
	                    		
	                    		{{ csrf_field() }}
	                    		@if(isset($dataTable))
		                    		@foreach($dataTable as $row)
					                    <div class="form-group row">
			                            	<label class="col-md-3 label-control" for="projectinput1">{{$row->name}}</label>
				                            <div class="col-md-9">
				                            	<input type="hidden" name="pid[]" value="{{$row->id}}">
				                            	<input type="number" id="projectinput1" class="form-control" placeholder="Enter quantity | Leave it empty if you dont want it on variance report." name="quantity[]">
				                            </div>
				                        </div>
			                        @endforeach
		                        @endif
		                        
	                        <div class="form-actions right">
								<button type="button" class="btn btn-green btn-accent-2 mr-1" @if($userguideInit==1) data-step="3" data-intro="if you want clear all information then click the clear button." @endif>
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
</section>
@endsection