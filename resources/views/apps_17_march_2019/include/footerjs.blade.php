<?php 
    $dataslideCheck=1;
    $dataslideCheck=StaticDataController::slideCheck();
    //dd($dataMenuAssigned);
?>
    
<!-- BEGIN VENDOR JS-->
    <!-- build:js app-assets/js/vendors.min.js-->
    <script src="{{url('theme/app-assets/js/core/libraries/jquery.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/tether.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/js/core/libraries/bootstrap.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/perfect-scrollbar.jquery.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/unison.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/blockUI.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/jquery.matchHeight-min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/jquery-sliding-menu.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/sliders/slick/slick.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/ui/screenfull.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/extensions/pace.min.js')}}" type="text/javascript"></script>
    <!-- /build-->
    <!-- BEGIN VENDOR JS-->
    <!-- BEGIN PAGE VENDOR JS-->
    <!-- END PAGE VENDOR JS-->
    <!-- BEGIN ROBUST JS-->
    <!-- build:js app-assets/js/app.min.js-->
    <script src="{{url('theme/app-assets/js/core/app-menu.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/js/core/app.min.js')}}" type="text/javascript"></script>
    <!-- /build-->

    <!-- START CORE PLUGINS -->
<script type="text/javascript">
        function logoutFRM()
        {
                $("#logoutME").submit();
        }

        function copyToClipboard(element) {
          var $temp = $("<input>");
          $("body").append($temp);
          $temp.val($(element).text()).select();
          document.execCommand("copy");
          $temp.remove();
          alert("Counter display link copied successfully.");
        }

        console.log("menu status","<?php echo $dataslideCheck; ?>");

        @if($dataslideCheck==2)
            $("body").removeClass("page-sidebar-minimize menu-collapsed");
        @else
            $("body").addClass("page-sidebar-minimize menu-collapsed");
        @endif

        function checkIdleUser()
        {
            //------------------------Ajax POS Start-------------------------//
            var AddIdlePOSUrl="{{url('check/idle/user')}}";
            $.ajax({
                'async': false,
                'type': "POST",
                'global': false,
                'dataType': 'json',
                'url': AddIdlePOSUrl,
                'data': {'_token':"{{csrf_token()}}"},
                'success': function (data) {
                    //tmp = data;
                    console.log("User Idle Check : "+data);
                },
                error: function(xhr, status, error) {
                    window.location.href="{{url('home')}}";
                }
            });
            //------------------------Ajax POS End---------------------------//
        }

        $(document).ready(function(){
            $("#fullscreen").click(function(){
                $(document).toggleFullScreen()
                $(this).children("i").toggleClass("danger");
            });

            $(".copyButton").click(function(){
                copyToClipboard('#cdlDt');
            });
            
            $(".cash_register_collapse").click(function(){
                //---------------------Ajax New Product Start---------------------//
                var AddProductUrl="{{url('slide-menu/slide/status')}}";
                $.ajax({
                    'async': false,
                    'type': "POST",
                    'global': false,
                    'dataType': 'json',
                    'url': AddProductUrl,
                    'data': {'slide':1,'_token':"{{csrf_token()}}"},
                    'success': function (data) { 
                        console.log(data);
                    }
                });
                //-----------------Ajax New Product End------------------//
            });

           


        });

        $(document).ready(function () {
            var idleState = false;
            var idleTimer = null;
            $('*').bind('mousemove click mouseup mousedown keydown keypress keyup submit change mouseenter scroll resize dblclick', function () {
                clearTimeout(idleTimer);
                if (idleState == true) { 
                    console.log('User Still Logged In.');          
                }
                idleState = false;
                idleTimer = setTimeout(function () { 
                    //$("body").css('background-color','#000');

                    checkIdleUser();

                    idleState = true; }, 603000); //600000
            });
            $("body").trigger("mousemove");
        });




        
    </script>

<!-- BEGIN Custom CSS-->
<script src="{{url('theme/sidebar/assets/js/jquery.cookie.js')}}"></script>
<script src="{{url('theme/sidebar/assets/js/handlebars.js')}}"></script>
<script src="{{url('theme/sidebar/assets/js/typeahead.bundle.min.js')}}"></script>
<script src="{{url('theme/sidebar/assets/js/jquery.nicescroll.min.js')}}"></script>


<!-- END Custom CSS-->
    <!--/ END CORE PLUGINS -->


    <!-- START PAGE LEVEL SCRIPTS -->
    <script src="{{url('theme/sidebar/assets/js/apps.js')}}"></script>
    
    <script src="{{url('theme/sidebar/assets/js/demo.js')}}"></script>
    <!--/ END PAGE LEVEL SCRIPTS -->


    <!-- END ROBUST JS-->
    <!-- BEGIN PAGE LEVEL JS-->
    <!-- END PAGE LEVEL JS-->
