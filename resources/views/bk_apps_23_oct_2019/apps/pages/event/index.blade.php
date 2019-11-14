@extends('apps.layout.master')
@section('title','Event Calender')
@section('content')
<?php 
    $userguideInit=StaticDataController::userguideInit();
?>
<section id="extra-examples">
    <div class="row">
        <div class="col-xs-12">
            <div class="card">
                <div class="card-header" @if($userguideInit==1) data-step="1" data-intro="Event calender is available here, all schedule will be displayed datewise." @endif>
                    <h4 class="card-title"><i class="icon-calendar4"></i> Event Calendar</h4>
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
                        <div id='fc-simplePOS'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</section>
@endsection

@include('apps.include.datatable',['eventCalender'=>1])