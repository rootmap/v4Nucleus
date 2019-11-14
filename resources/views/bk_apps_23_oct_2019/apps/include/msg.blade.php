@if (session('status'))
    <div class="alert successPlace bg-success alert-icon-left alert-arrow-left alert-dismissible fade in mb-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        <strong>Well done!</strong> {{ session('status') }}
    </div>
    <script type="text/javascript">
        setTimeout(function(){
            $('.successPlace').fadeOut('slow');
        }, 5000);
    </script>
    <?php 
    Session::forget('status');
    ?>
@endif

@if (session('success'))
    <div class="alert successPlace bg-success alert-icon-left alert-arrow-left alert-dismissible fade in mb-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        <strong>Well done!</strong> {{ session('success') }}
    </div>
    <script type="text/javascript">
        setTimeout(function(){
            $('.successPlace').fadeOut('slow');
        }, 5000);
    </script>
    <?php 
    Session::forget('success');
    ?>
@endif

@if (session('error'))
    <div class="alert allDanger bg-danger alert-icon-left alert-arrow-left alert-dismissible fade in mb-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        <strong>Oh snap!</strong> {{ session('error') }}
    </div>
    <script type="text/javascript">
        setTimeout(function(){
            $('.allDanger').fadeOut('slow');
        }, 5000);
    </script>
    <?php 
    Session::forget('error');
    ?>
@endif

@if (count($errors) > 0)
     @foreach ($errors->all() as $error)
    <div class="alert allDanger bg-danger alert-icon-left alert-arrow-left alert-dismissible fade in mb-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        <strong>Oh snap!</strong> {{ $error }}
    </div>
    @endforeach
    <script type="text/javascript">
        setTimeout(function(){
            $('.allDanger').fadeOut('slow');
        }, 5000);
    </script>
@endif