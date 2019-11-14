
@section('css')
	@if(isset($JDataTable))
    <link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/tables/datatable/dataTables.bootstrap4.min.css')}}">
    <style type="text/css">
		div.dataTables_length{
				padding-left: 10px;
				padding-top: 15px;
		}

		.dataTables_length>label{
			margin-bottom: 0px !important;
			display:block; !important;
		}

		div.dataTables_filter
		{
			padding-right: 10px;
		}

		div.dataTables_green{
			padding-left: 10px;
		}

		div.dataTables_paginate{
			padding-right: 10px;
			padding-top: 5px;
		}
	</style>
	@endif
	@if(isset($selectTwo))
    	<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/forms/selects/select2.min.css')}}">
    @endif

    @if(isset($dateDrop))
    	<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/extensions/pace.css')}}">
    	<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/extensions/datedropper.min.css')}}">
    @endif

    @if(isset($eventCalender))
    	<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/vendors/css/calendars/fullcalendar.min.css')}}">
    	<link rel="stylesheet" type="text/css" href="{{url('theme/app-assets/css/plugins/calendars/fullcalendar.min.css')}}">
    @endif
    
@endsection

@section('js')
	@if(isset($invoiceSlip))
	<script type="text/javascript">
		function putInvoiceModal(invNo)
		{
			$(".invoice_slip_id").val(invNo);
			$("#slip").modal('show');
		}
	</script>
	@endif

	@if(isset($buyback_customer))
	<script type="text/javascript">
	

	$("select[name=customer_id]").change(function(){
        var customerID=$.trim($(this).val());
        console.log(customerID);
        if(customerID.length==0)
        {
            alert("Please select a customer.");
            return false;
        }
        else if(customerID==0)
        {
            $("#NewCustomerDash").modal('show');
            return false;
        }
    });

    $(".save-new-customer").click(function(){

                    var name=$.trim($("input[name=new_customer_name]").val());
                    var phone=$.trim($("input[name=new_customer_phone]").val());
                    var email=$.trim($("input[name=new_customer_email]").val());
                    var address=$.trim($("input[name=new_customer_address]").val());
                    //console.log(name,phone,email,address);
                    if(name.length==0)
                    {
                        alert("Please select a customer Name.");
                        return false;
                    }
                    else if(phone.length==0)
                    {
                        alert("Please select a customer Phone Number.");
                        return false;
                    }
                    /*else if(email.length==0)
                    {
                        alert("Please select a customer Email.");
                        return false;
                    }
                    else if(address.length==0)
                    {
                        alert("Please select a customer Address.");
                        return false;
                    }*/
                    
                    $(".save-new-customer-parent").html(" Processing please wait.....");

                    //------------------------Ajax Customer Start-------------------------//
                    var AddNewCustomerUrl="{{url('customer/pos/ajax/add')}}";
                    $.ajax({
                        'async': false,
                        'type': "POST",
                        'global': false,
                        'dataType': 'json',
                        'url': AddNewCustomerUrl,
                        'data': {'name':name,'phone':phone,'email':email,'address':address,'_token':"{{csrf_token()}}"},
                        'success': function (data) {
                            $("select[name=customer_id]").append('<option value="'+data+'">'+name+'</option>');
                            $("select[name=customer_id] option[value='"+data+"']").prop("selected",true);

                            console.log("Saved New Customer : "+data);
                            $("#NewCustomerDash").modal('hide');

                        }
                    });
                    //------------------------Ajax Customer End---------------------------//
                });


    </script>
    @endif
	
	@if(isset($JDataTable))
    <script src="{{url('theme/app-assets/vendors/js/tables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
    <script src="{{url('theme/app-assets/vendors/js/tables/datatable/dataTables.bootstrap4.min.js')}}" type="text/javascript"></script>

    <!-- BEGIN PAGE LEVEL JS-->
    <script src="{{url('theme/app-assets/js/scripts/tables/datatables/datatable-basic.min.js')}}" type="text/javascript"></script>
    <!-- END PAGE LEVEL JS-->
    @endif
    @if(isset($selectTwo))
    	<script src="{{url('theme/app-assets/vendors/js/forms/select/select2.full.min.js')}}" type="text/javascript"></script>
		<script src="{{url('theme/app-assets/js/scripts/forms/select/form-select2.min.js')}}" type="text/javascript"></script>
    @endif
    @if(isset($eventCalender))
    	<script src="{{url('theme/app-assets/vendors/js/extensions/moment.min.js')}}" type="text/javascript"></script>
    	<script src="{{url('theme/app-assets/vendors/js/extensions/fullcalendar.min.js')}}" type="text/javascript"></script>
		<script type="text/javascript">
			$(document).ready(function() {
				var calDatra= jQuery.parseJSON('<?php echo $calData; ?>');
				console.log(calDatra);
			    $("#fc-simplePOS").fullCalendar({
			        defaultDate: "{{date('Y-m-d')}}",
			        editable: !0,
			        eventLimit: !0,
			        events: calDatra
			    });
			});
		</script>
    @endif

    @if(isset($barcodejs))
    	@include('apps.include.json.barcode')
    @endif    

    @if(isset($instorerepairjson))
    	@include('apps.include.json.instorerepair')
    @endif    

    @if(isset($instorerepairajax))
    	@include('apps.include.json.instorerepairajax')
    @endif    

    @if(isset($instorerepairajaxinfo))
    	@include('apps.include.json.instorerepairajaxinfo')
    @endif   

    @if(isset($posinstorerepair))
    	{{-- Instore repair js * Start  --}}
		@include('apps.include.json.posinstorerepair')
		@include('apps.include.modal.jsalert')
		{{-- Instore repair js * End  --}}
		<script type="text/javascript">
			$(document).ready(function(){
				$("#step_1").click(function(){
					//alert("gg");
					$(".step1").hide();
					$("#step_1").hide();

					$("#step_2_to_step_1").show();
					$("#step_2").show();
					$(".step2").show();
					$('select[name=customer_id]').val('').select2();
					$(".step2_repair_new_customer").hide();

				});

				$("#step_2_to_step_1").click(function(){
					//alert("gg");
					$(".step1").show();
					$("#step_1").show();

					$("#step_2_to_step_1").hide();
					$("#step_2").hide();
					$(".step2").hide();

					$('select[name=customer_id]').val('').select2();
					$(".step2_repair_new_customer").hide();

				});

				$("#step_3_to_step_2").click(function(){
					//alert("gg");
					$(".step1").hide();
					$("#step_1").hide();

					$("#step_2_to_step_1").show();
					$("#step_2").show();
					$(".step2").show();

					$('select[name=customer_id]').val('').select2();
					$(".step2_repair_new_customer").hide();

				});


				$("select[name=customer_id]").change(function(){
					var cusID=$(this).val();
					if(cusID=="CR000")
					{
						$(".step2_repair_new_customer").show();
					}
					else
					{
						$(".step2_repair_new_customer").hide();
					}
				});

				$("#step_2").click(function(){ 
					$("#step_2_to_step_1").hide();
				});
			});
			
		</script>
    @endif

    @if(isset($mergeJS))
    	@include('apps.include.json.mergeJS')
    @endif

    @if(isset($expenseHeadCreate))
    	<script type="text/javascript">
			$(document).ready(function() {
				$(".expense_head_name").hide();
			    $("select[name=expense_id]").change(function(){
			    	var expenseId=$(this).val();
			    	if(expenseId=='00')
			    	{
			    		$(".expense_id").fadeOut('fast');
			    		$(".expense_head_name").fadeIn('slow');
			    		
			    	}
			    });
			});
	    </script>
    @endif 

    @if(isset($customer_lead))
    	<script type="text/javascript">
			$(document).ready(function(){
				$("select[name=customer_id]").change(function(){
		            var customerID=$.trim($(this).val());
		            console.log(customerID);
		            if(customerID.length==0)
		            {
		            	$(".nameField").hide();
		                alert("Please select a customer.");
		                return false;
		            }
		            else if(customerID==0)
		            {
		            	$(".nameField").show();
		                //$("#NewCustomerDash").modal('show');
		                return false;
		            }


		            //------------------------Ajax Customer Start-------------------------//
		            var AddCustomerUrl="{{url('customer/getInfo/json')}}/"+customerID;
		            $.ajax({
		                'async': false,
		                'type': "GET",
		                'global': false,
		                'dataType': 'json',
		                'url': AddCustomerUrl,
		                'success': function (data) {
		                    console.log("Assigning custome to cart : "+data);
		                    var obj=data;
		                    console.log(obj);
		                    var customerID=obj.id;
		                    //var customerIDLebgth=customerID.length;
		                    console.log(obj.id);
		                    if(obj.id)
		                    {
		                    	$(".nameField").hide();
		                    	$("input[name=name]").val(obj.name);
		                    	$("input[name=address]").val(obj.address);
		                    	$("input[name=phone]").val(obj.phone);
		                    	$("input[name=email]").val(obj.email);
		                    }
		                }
		            });
		            //------------------------Ajax Customer End---------------------------//
		        });
			});
		</script>
    @endif

    @if(isset($dateDrop))
    	<script src="{{url('theme/app-assets/vendors/js/extensions/pace.min.js')}}" type="text/javascript"></script>
	    <!-- /build-->
	    <!-- BEGIN VENDOR JS-->
	    <!-- BEGIN PAGE VENDOR JS-->
	    <script src="{{url('theme/app-assets/vendors/js/extensions/datedropper.min.js')}}" type="text/javascript"></script>
	    <!-- END PAGE VENDOR JS-->

	    <!-- BEGIN PAGE LEVEL JS-->
	     <script type="text/javascript">
			$(document).ready(function() {
			    $(".DropDateWithformat").dateDropper({
			        dropWidth: 200,
			        maxYear: "<?=date('Y')?>",
			        minYear: "2010",
			        format: "Y-m-d",
			        init_animation: "bounce",
			        dropPrimaryColor: "#fa4420",
			        dropBorder: "1px solid #fa4420",
			        dropBorderRadius: "20",
			        dropShadow: "0 0 10px 0 rgba(250, 68, 32, 0.6)"
			    });
			});
	    </script>
	    <!-- END PAGE LEVEL JS-->
    @endif

    @if(isset($confirmStockIN))
    	<script type="text/javascript">
			$(document).ready(function(){
				$(".typed_quantity").keyup(function(){
					var currrentQty=$(this).val();
					if(currrentQty.length==0)
					{
						currrentQty=0;
						$(this).val('0');
					}

					if(currrentQty<=0)
					{
						currrentQty=0;
						$(this).val('0');
					}
					//alert(unitPrice);
					genarateSL();
				});
				$(".typed_quantity").change(function(){
					var currrentQty=$(this).val();
					if(currrentQty.length==0)
					{
						currrentQty=0;
						$(this).val('0');
					}

					if(currrentQty<=0)
					{
						currrentQty=0;
						$(this).val('0');
					}
					genarateSL();
				});

				$("#invoiceSubmit").click(function(){
					var order_no=$("input[name=order_no]").val();
					var order_date=$("input[name=order_date]").val();

					if(order_no.length==0)
					{
						alert('Please Type a Order No.!!!');
						return false;
					}

					if(order_date.length==0)
					{
						alert('Please select a Date.!!!');
						return false;
					}

					$("#createInvoice").submit();
				});



			});


			function removeRowCart(cartID)
			{
				$('#row_'+cartID).fadeOut();
				$('#row_'+cartID).remove();
				genarateSL();
			}

			function genarateSL()
			{	
				var totalQuantity=0;
				var total=$('.sl').size();
				if(total)
				{
					$('.sl').each(function(index){
						
						$(this).html((index-0)+(1-0));
						var rowtotal=$(this).parent('tr').find('.typed_quantity').val();
						if(rowtotal<=0)
						{
							totalQuantity+=0;
							$(this).parent('tr').find('.typed_quantity').val('0');
						}
						else{
							totalQuantity+=(rowtotal-0);
						}

					});
				}
				$("#shoppingCartQuantityTotal").html(totalQuantity);
			}

			
		</script>
    @endif

    @if(isset($testJsonApi))
    	@include('apps.include.api.test')
    @endif

    @if(isset($view_buyback))
    	@include('apps.include.json.buyback')
    @endif

    @if(isset($view_repair))
    	@include('apps.include.json.view_repair')
    @endif

    @if(isset($view_ticket))
    	@include('apps.include.json.view_ticket')
    @endif    

    @if(isset($order_parts))
    	<script type="text/javascript">
    		$(document).ready(function(){
    			$("select[name=ticket_id]").change(function(){
    				var ticket_id=$(this).val();
    				var defineValue=$("select[name=ticket_id] option[value="+ticket_id+"]").attr("data-value");
    				//alert(defineValue);
    				$("input[name=ticket_payment_status]").val(defineValue);
    			});
    		});
    	</script>
    @endif

    @if(isset($storecloseDetailReport))
    	<script type="text/javascript">
    		function openCloseStoreInfo(closingID)
    		{
    			//------------------------Ajax New Product Start-------------------------//
	            var AddProductUrl="{{url('storeclose/detail')}}";
	            $.ajax({
	                'async': true,
	                'type': "POST",
	                'global': false,
	                'dataType': 'json',
	                'url': AddProductUrl,
	                'data': {'storecloseid':closingID,'_token':"{{csrf_token()}}"},
	                'success': function (data) { 
	                    console.log("Assigning Discount : "+data);
	                    $("#storeOpenDate").html(data.OpenDate);
	                    $("#storeCloseDate").html(data.CloseDate);
	                    $("#storeCloseTotalCollection").html(data.totalCollection);
	                    $("#storeCloseOpeningAmount").html(data.openingAmount);
	                    $("#totalPayout").html(data.payout);
	                    $("#storeCloseTaxAmount").html(data.tax);
	                    $("#currectStoreTotal").html(data.netTotal);
	                    $("#buybackStoreClosingAmount").html(data.buyback);

	                    var tenderData=data.tnderData;

	                    var htmlStr="";
	                    if(tenderData.length)
	                    {
	                    	
	                    	$.each(tenderData,function(index,row){
	                    		htmlStr +='<tr><td align="left">'+row.tender_name+' (+) :  </td><td align="left">$'+row.tender_total+'</td></tr>';
	                    	});

	                    }
	                    $("#storeCloseTableTenderList").html(htmlStr);
	                }
	            });
	            //------------------------Ajax New Product End---------------------------//
    			$("#close-drawer-detail").modal('show');
    		}
    		$(document).ready(function(){
    			
    		});
    	</script>
    @endif

    @if(isset($ticket))
    	@include('apps.include.json.ticket')
    	<script type="text/javascript">

    	function loadingOrProcessing(sms)
	    {
	        var strHtml='';
	            strHtml+='<div class="alert alert-icon-right alert-green alert-dismissible fade in mb-2" role="alert">';
	            strHtml+='      <i class="icon-spinner10 spinner"></i> '+sms;
	            strHtml+='</div>';

	            return strHtml;

	    }

	    function warningMessage(sms)
	    {
	        var strHtml='';
	            strHtml+='<div class="alert alert-icon-left alert-danger alert-dismissible fade in mb-2" role="alert">';
	            strHtml+='<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
	            strHtml+='<span aria-hidden="true">×</span>';
	            strHtml+='</button>';
	            strHtml+=sms;
	            strHtml+='</div>';
	            return strHtml;
	    }

	    function successMessage(sms)
	    {
	        var strHtml='';
	            strHtml+='<div class="alert alert-icon-left alert-success alert-dismissible fade in mb-2" role="alert">';
	            strHtml+='<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
	            strHtml+='<span aria-hidden="true">×</span>';
	            strHtml+='</button>';
	            strHtml+=sms;
	            strHtml+='</div>';
	            return strHtml;
	    }

    	$(document).ready(function(){
    		$("select[name=ticket_customer_id]").change(function(){
    			var customerID=$.trim($(this).val());
	            console.log(customerID);
	            if(customerID=="CR000")
	            {
	                $("#NewCustomerDash").modal('show');
	                return false;
	            }

    		});

    		$(".save-new-customer").click(function(){
    			//alert("hhh");
	            var name=$.trim($("input[name=new_customer_name]").val());
	            var phone=$.trim($("input[name=new_customer_phone]").val());
	            var email=$.trim($("input[name=new_customer_email]").val());
	            var address=$.trim($("input[name=new_customer_address]").val());
	            //console.log(name,phone,email,address);
	            if(name.length==0)
	            {
	                alert("Please select a customer Name.");
	                return false;
	            }
	            else if(phone.length==0)
	            {
	            	alert("Please select a customer Phone Number.");
	                return false;
	            }
	            
	            alert("Processing please wait.....");
	            //------------------------Ajax Customer Start-------------------------//
	            var AddNewCustomerUrl="{{url('customer/pos/ajax/add')}}";
	            $.ajax({
	                'async': false,
	                'type': "POST",
	                'global': false,
	                'dataType': 'json',
	                'url': AddNewCustomerUrl,
	                'data': {'name':name,'phone':phone,'email':email,'address':address,'_token':"{{csrf_token()}}"},
	                'success': function (data) {
	                    $("select[name=ticket_customer_id]").append('<option value="'+data+'">'+name+'</option>');
	                    $("select[name=ticket_customer_id] option[value='"+data+"']").prop("selected",true);
	                    
	                    $("#ticketMSG").html(successMessage("Successfully Saved Customer Info."));
	                    alert("Successfully Saved Customer Info.");
	                    $("#NewCustomerDash").modal('hide');
	                }
	            });
	            //------------------------Ajax Customer End---------------------------//
	        });



    	});

    	



    	</script>
    @endif

    <script type="text/javascript">
    	$(document).ready(function(){
    		$(".change-action").click(function(){
				var getURL=$(this).attr("data-url");
				console.log(getURL);
				//return false;

				$("form").attr("action",getURL);
				//console.log($("select[name=customer_id]").val());
				
				$("button[type=submit]").click();
			});
    	});
    </script>

    <script type="text/javascript">
    	$(document).ready(function(){
    		$(".change-action-export-sales").click(function(){
				var getURL=$(this).attr("data-url");
				console.log(getURL);
				//return false;

				$("#salesSu").attr("action",getURL);
				//console.log($("select[name=customer_id]").val());
				
				$("#salesSUSub").click();
			});
    	});
    </script>

@endsection