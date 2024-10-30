jQuery(function ($) {
    jQuery("[data-toggle='switch']").bootstrapSwitch();
    $('[data-toggle="checkbox"]').radiocheck();
    $('[data-toggle="radio"]').radiocheck();
    $('[data-toggle="tooltip"]').tooltip();
    $(document).on('change', 'input:radio[name="where"]', function (event) {
        //alert("click fired"+$('#radio2').is(':checked'));
        if ($('#radio2').is(':checked')) {
            $('input:checkbox[name="display[]"]').radiocheck('enable');
            if ($('#display4').is(':checked')) {
                $('.magicsuggest').show();
            }
        } else {
            //$('input:checkbox[name="display[]"]').radiocheck('disable');
            $('.magicsuggest').hide();
            $('input:checkbox[name="display[]"]').attr("checked", false);
        }
    });
    $(document).on('change', '#tenacious', function (event) {
        //alert("click fired"+$('#radio2').is(':checked'));
        if ($('#tenacious').is(':checked')) {
            if (!confirm("are you sure you want to permanently disable plugin")) {
                $('#tenacious').attr("checked", false);
            }
        }
    });
    $(document).on('change', '#display4', function (event) {
        if ($('#display4').is(':checked')) {
            $('.magicsuggest').show();
        } else {
            $('.magicsuggest').hide();
        }
    });
    
    $(document).on('click', 'input:checkbox[name="display[]"]', function (event) {
        if($(this).is(':checked')){
            $('#radio2').attr("checked", true);
        }
    });
    $(function () {
        //$('[data-toggle="tooltip"]').tooltip("show");
        //$(".tooltip.fade.top").remove();
    });
});

jQuery(document).ready(function ($) {
    $('#switchonoff').on('switchChange.bootstrapSwitch', function (event, state) {
        console.log(this); // DOM element
        console.log(event); // jQuery event
        console.log(state); // true | false
        if (state) {
            jQuery.post('', {'cldc_switchonoff': 1}, function (e) {
                if (e == 'error') {
                    error('error');
                } else {
                    jQuery('#cldc_circ').css("background", "#0f0");
                }
            });
        } else {
            jQuery.post('', {'cldc_switchonoff': 0}, function (e) {
                if (e == 'error') {
                    error('error');
                } else {
                    jQuery('#cldc_circ').css("background", "#f00");
                }
            });
        }
    });
});

jQuery(document).ready(function ($) {
    var current_url = window.location;
    var loc = window.location.href;
    index = loc.indexOf('#');

    if (index > 0) {
        current_url = loc.substring(0, index);
    }
    var magic_url = current_url + '&cldc_magic_data=1';
    console.log(magic_url);
    $('#magicsuggest').magicSuggest({
        data: magic_url,
        ajaxConfig: {
            xhrFields: {
                withCredentials: true,
            }
        }
    });
})