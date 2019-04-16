(function (a){
    $(document).on('click','[data-add]',function () {
        var obj = $(this).data('add');
        var w = $(window);
        a++;
        var row = $('#param'+((a-1)?a-1:'')).closest('.form-group');
        var html = '';
        html += '<div class="form-group">';
        html += '<label for="username" class="col-lg-2"></label>';
        html += '<div class="col-sm-3">';
        html += '<input type="text" id="param'+ a +'" class="form-control s_trade param" name="param_key[]" value="">';
        html += '</div>';
        html += '<div class="col-sm-3">';
        html += '<input type="text" class="form-control" name="param_value[]" >';
        html += '</div>';
        html += '<div class="col-sm-1"><span class="btn btn-default" data-add="reduce">-</span></div>';
        html += '</div>';
        $(html).insertAfter(row).find('[data-add]').on('click',function () {
            a--;
            $(this).closest('.form-group').remove();
        }).end();
        return false;
    })
})(1);