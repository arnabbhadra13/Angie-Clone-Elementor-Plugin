(function($){
    var planCache = null;
    function showOutput(obj){
        $('#angie-output').text(JSON.stringify(obj, null, 2));
    }
    function renderPlan(plan){
        planCache = plan;
        var $editor = $('#angie-plan-editor').empty();
        if(!Array.isArray(plan) || plan.length === 0){
            $editor.text('(no planned steps)');
            $('#angie-apply').prop('disabled', true);
            return;
        }
        $('#angie-apply').prop('disabled', false);
        plan.forEach(function(step, idx){
            var $row = $('<div>').css({padding:'8px',borderBottom:'1px solid #eee'});
            $row.append($('<strong>').text('['+ (step.type || 'step') +'] '));
            $row.append($('<span>').text(step.title || step.message || JSON.stringify(step)));
            $editor.append($row);
        });
    }
    $(document).ready(function(){
        $('#angie-preview').on('click', function(e){
            e.preventDefault();
            var prompt = $('#angie-prompt').val().trim();
            if(!prompt){ alert('Enter a prompt'); return; }
            $('#angie-preview').prop('disabled', true);
            $.ajax({
                url: AngieClone.previewUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ prompt: prompt }),
                beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', AngieClone.nonce); }
            }).done(function(resp){
                if(resp && resp.plan){
                    renderPlan(resp.plan);
                    showOutput({ preview: resp });
                } else {
                    showOutput(resp);
                }
            }).fail(function(jq){
                showOutput({ error: jq.responseText || jq.statusText });
            }).always(function(){ $('#angie-preview').prop('disabled', false); });
        });

        $('#angie-apply').on('click', function(e){
            e.preventDefault();
            if(!planCache){ alert('No plan to apply'); return; }
            $('#angie-apply').prop('disabled', true);
            $.ajax({
                url: AngieClone.applyUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ plan: planCache }),
                beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', AngieClone.nonce); }
            }).done(function(resp){
                showOutput(resp);
            }).fail(function(jq){
                showOutput({ error: jq.responseText || jq.statusText });
            }).always(function(){ $('#angie-apply').prop('disabled', false); });
        });
    });
})(jQuery);
