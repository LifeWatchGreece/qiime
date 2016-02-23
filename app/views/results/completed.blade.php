<div class="top-label">
    Job{{ $job->id }} Information/Results 
    <img src="{{ asset('images/script1.png') }}" style="width: 25px; float: right" class="view-script-icon" title="View Qiime script">
    <a href="{{ url('/') }}" style="float:right; margin-right: 10px; font-size: 19px" title="Home Page"><span class="glyphicon glyphicon-home" aria-hidden="true"></span></a>
</div>
<br>
<div class="completed_wrapper">
    
    <div class="panel panel-default" style="margin-top: 20px">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-log-in" aria-hidden="true" style="margin-right: 5px"></span> 
            <strong>Input files</strong>
        </div>
        <div class="panel-body">
            <table class="table table-hover table-condensed no-border-top">
                @foreach($input_files as $ifile)
                <tr>
                    <td style="text-align: left">{{ $ifile['filename'] }}</td>
                    <td style="width:20%; text-align: right"> 
                        @if($ifile['exists'])
                            <a href="{{ url('workspace/get/'.$ifile['filename']) }}" style="outline:0" download>
                                <img src="{{ asset('images/download2.png') }}" class="link-icon" title="Download file">
                            </a>
                        @else
                        <span style="color: #CD3F3F">Was deleted!</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>   
    
    @if(!empty($dir_prefix))

        <div class="panel panel-default" style="margin-top: 20px">
            <div class="panel-heading">
                <span class="glyphicon glyphicon-log-out" aria-hidden="true" style="margin-right: 5px"></span> 
                <strong>Files produced as output</strong>
            </div>
            <div class="panel-body">
                <table class="table table-hover table-condensed no-border-top">
                    <tr>
                        <td style="text-align: left">Download all QIIME results</td>
                        <td style="width:20%; text-align: right">                            
                            <a href="{{ url('storage/get_job_file/job/'.$job->id.'/qiime.tgz') }}" style="outline:0" download>
                                <img src="{{ asset('images/download2.png') }}" class="link-icon" title="Download file">
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>   

    @endif
    
    <div class="panel panel-default" style="margin-top: 20px">
            <div class="panel-heading" id="workspace-panel-heading">
                <span class="glyphicon glyphicon-pencil" aria-hidden="true" style="margin-right: 5px"></span> 
                <strong>Text output</strong>
                <div class="routput-glyphicon">
                    <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
                </div>
            </div>
        <div class="panel-body" style="background-color: #F2F3F9" id="routput-panel-body">
            @if(!empty($content))
                {{ $content }}
            @endif            

            @foreach($lines as $line)
                {{ str_replace(" " , "&nbsp" ,$line) }} <br>
            @endforeach

            <br>
    
        </div>
    </div>        

    <div class="panel panel-default" style="margin-top: 20px">
        <div class="panel-heading" id="rimages-panel-heading">
            <span class="glyphicon glyphicon-picture" aria-hidden="true" style="margin-right: 5px"></span> 
            <strong>Graph output</strong>
            <div class="rimages-glyphicon">
                <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
            </div>
        </div>
        <div class="panel-body" style="background-color: #F2F3F9" id="rimages-panel-body">
            
            <div class="plot_buttons_div">
                <a href="{{ url('job/'.$job->id.'/html/rarefraction') }}">
                    <img src="{{ asset('images/rarefaction.png') }}">
                    <div>Rarefaction</div>
                </a>           
                <div style="clear: both"></div>
            </div>
            <div style="font-weight: bold; margin:3px 0px">Taxa summary plots</div>
            <div class="plot_buttons_div">
                <a href="{{ url('job/'.$job->id.'/html/bar') }}">
                    <img src="{{ asset('images/bar.png') }}">
                    <div>bar</div>
                </a> 
                <a href="{{ url('job/'.$job->id.'/html/pie') }}">
                    <img src="{{ asset('images/pie.png') }}">
                    <div>pie</div>
                </a> 
                <a href="{{ url('job/'.$job->id.'/html/area') }}">
                    <img src="{{ asset('images/area.png') }}">
                    <div>area</div>
                </a> 
                <a href="{{ url('storage/get_job_file/job/'.$job->id.'/otu_table_heatmap.pdf') }}">
                    <img src="{{ asset('images/heatmap.png') }}">
                    <div>Heatmap</div>
                </a> 
                <a href="{{ url('storage/get_job_file/job/'.$job->id.'/jack;unweighted_unifrac;upgma_cmp/jackknife_named_nodes.pdf') }}">
                    <img src="{{ asset('images/jackknife.png') }}">
                    <div>Jackknife tree</div>
                </a> 
                @if(file_exists($job_folder.'/wf_bdiv_even1820/unweighted_unifrac_emperor_pcoa_plot/index.html'))                    
                    <a href={{ url('job/'.$job->id.'/html/diversity') }}>
                @else                   
                    <a href={{ url('job/'.$job->id.'/html/diversity2') }}> 
                @endif                
                    <img src="{{ asset('images/xyz.png') }}">
                    <div>Beta-diversity</div>
                </a>
                <div style="clear: both"></div>
            </div>            
        </div>
    </div>     

    <img src="{{ asset('images/loading.gif') }}" style="display:none" id="loading-image" />

    <div class="panel panel-default" id="r-script-panel" style="display: none">
        <div class="panel-heading">
            <strong>Qiime script</strong>
            <span class="glyphicon glyphicon-remove" style="float:right; color: red" aria-hidden="true" id="close-r-panel"></span>
        </div>
      <div class="panel-body" style="height: 350px; overflow: auto">

      </div>
    </div>
</div>



<script type="text/javascript">       

    function add_output_to_workspace(filename,jobId,elementId){

        var postData = { 
            filename: filename, 
            jobid: jobId, 
        };

        $('#loading-image').center().show();  
        $.ajax({
            url : '{{ url("workspace/add_output_file") }}',
            type: "POST",
            data : postData,
            dataType : 'json',
            success:function(data, textStatus, jqXHR) 
            {
                toastr.success('File moved to your workspace successfully!');                
            },
            error: function(jqXHR, textStatus, errorThrown) 
            {
                switch (jqXHR.status) {
                    case 400: // Form validation failed
                        toastr.error('Invalid request! File was not moved to your workspace!'); 
                        break;
                     case 401: // Unauthorized access
                        toastr.error('Unauthorized access!');
                        break;
                    case 428: // Target file name already exists
                        toastr.error('A file with such a name already exists in your workspace!');
                        break;
                     case 500: // Unexpected error
                        toastr.error("An unexpected error occured! Please contact system adminnistrator.");
                        break;
                }
            },
            complete: function(){
              $('#loading-image').hide();
            }
        });
    }

    $('#close-r-panel').click(function(){
        $('#r-script-panel').hide();
    });       

     $('.routput-glyphicon').click(function(){
        $('#routput-panel-body').slideToggle();
        $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
     });       
     
     $('.rimages-glyphicon').click(function(){
        $('#rimages-panel-body').slideToggle();
        $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
     });  
         
    $('.view-script-icon').click(function(){    
        $('#loading-image').center().show();  
        $.ajax({
            url : '{{ url("storage/get_qiime_script/".$job->id) }}',
            type: "GET",
            dataType : 'json',
            success:function(data, textStatus, jqXHR) 
            {
                $('#r-script-panel .panel-body').empty();
                for(var i = 0; i < data.length; i++) {
                    $('#r-script-panel .panel-body').append(data[i]+"<br>");
                }
                $('#r-script-panel').center().show();          
            },
            error: function(jqXHR, textStatus, errorThrown) 
            {
                switch (jqXHR.status) {
                    case 400: // Form validation failed
                        alert("Qiime script could not be found");
                        break;
                     case 401: // Unauthorized access
                        alert("You don't have access to this Qiime script.");
                        break;
                     case 500: // Unexpected error
                        alert("Qiime script could not be retrieved.");
                        break;
                }
            },
            complete: function(){
              $('#loading-image').hide();
            }
        });  
    });            
       
</script>       