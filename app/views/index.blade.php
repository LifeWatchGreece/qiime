<script type='text/javascript'>
    
    function delete_job(jobId){
        $( "#deleteJob"+jobId+"Form" ).submit();
    }
    
</script>    

@if(!empty($deletion_info))
<div class="row">    
    @if($deletion_info['total'] == $deletion_info['deleted'])
        <div class='col-sm-12'>
            <div class='alert alert-success alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                All selected jobs deleted successfully!
            </div>
        </div>
    @elseif($deletion_info['deleted'] > 0)
        <div class='col-sm-12'>
            <div class='alert alert-warning alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                <strong>Warning!</strong> Some jobs couldn't be deleted!
            </div>
        </div>
        @foreach($deletion_info['messages'] as $message)
            <div class='col-sm-12'>
                <div class='alert alert-danger alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error:</strong> {{ $message }}
                </div>
            </div>
        @endforeach
    @else
        <div class='col-sm-12'>
            <div class='alert alert-danger alert-dismissible' role='alert'>
                <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                <strong>Error:</strong> None of the selected jobs could be deleted!
            </div>
        </div>
        @foreach($deletion_info['messages'] as $message)
            <div class='col-sm-12'>
                <div class='alert alert-danger alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error:</strong> {{ $message }}
                </div>
            </div>
        @endforeach
    @endif
</div>
@endif

<div class="row">
    <div class="col-sm-12">
        <div style="text-align: justify">
            This is a web interface to <b>QIIME (Quantitative insights in Microbial Ecology)</b> which is running on a PC cluster part of 
            the <b>LifeWatch Genetics services </b>infrastructure at the Institute of Marine Biology, Biotechnology and Aquaculture (IMBBC), 
            Hellenic Centre for Marine  Research (HCMR), Heraklion, Greece.
        </div>
    </div>
</div>

<div class="row">
    
    @if($is_admin)
        @include("admin.bar")
    @endif     
    
    <div class="col-sm-6">
         
        <div class="panel panel-default" style="margin-top: 20px">
            <div class="panel-heading" id="workspace-panel-heading">
                <span class="glyphicon glyphicon-file" aria-hidden="true"></span> 
                <strong>Workspace File Management</strong>
                <div class="workspace-glyphicon">
                    <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
                </div>
            </div>
            @if($workspace_tab_status == 'closed')
                <div class="panel-body" style="display: none; background-color: #F2F3F9" id="workspace-panel-body">
                    {{ $workspace }}             
                </div>
            @else
                <div class="panel-body" style="background-color: #F2F3F9" id="workspace-panel-body">
                    {{ $workspace }}             
                </div>
            @endif                        
        </div> 
        
        <div style="font-weight: bold; margin-bottom: 5px">Recent Jobs:</div>
        <div id="recent-jobs-wrapper">
            <table class="table table-bordered table-condensed">
                <thead>
                    <th>Job ID</th>
                    <th>Status</th>
                    <th class='job_date_column'>Submitted At <span class="glyphicon glyphicon-retweet change_column_icon" aria-hidden="true" title='change to job size column'></span></th>
                    <th class='job_size_column' style='display: none'>Folder Size <span class="glyphicon glyphicon-retweet change_column_icon" aria-hidden="true" title='change to job date column'></span></th>
                    <th></th>
                </thead>
                <tbody>
                    @if(empty($job_list))
                    <tr>
                        <td colspan="4">No job submitted recently</td>
                    </tr>
                    @endif
                    @foreach($job_list as $job)
                    <tr>
                        <td>{{ link_to('job/'.$job->id,'Job'.$job->id) }}</td>
                        <td style="text-align: center">
                            <?php
                                switch($job->status){
                                    case 'creating':
                                        echo "<div class='job_status status_creating'>Creating...</div>";
                                        break;
                                    case 'submitted':
                                        echo "<div class='job_status status_submitted'>Submitted</div>";
                                        break;
                                    case 'queued':
                                        echo "<div class='job_status status_queued'>Queued</div>";
                                        break;
                                    case 'running':
                                        echo "<div class='job_status status_queued'>Running</div>";
                                        break;
                                    case 'completed':
                                        echo "<div class='job_status status_completed'>Completed</div>";
                                        break;
                                    case 'failed':
                                        echo "<div class='job_status status_failed'>Failed</div>";
                                        break;
                                }
                            ?>
                        </td>
                        <td class='job_date_column'>{{ $job->submitted_at }}</td>
                        <td class='job_size_column' style='display: none'>
                            @if($job->jobsize > 1000000)
                                {{ number_format($job->jobsize,2) }} GB
                            @elseif($job->jobsize > 1000)
                                {{ number_format($job->jobsize,2) }} MB
                            @else
                                {{ number_format($job->jobsize,2) }} KB
                            @endif
                        </td>
                        <td style="min-width: 20px">
                             @if(in_array($job->status,array('completed','failed','creating')))
                                <input type="checkbox" id="job_checkbox_{{ $job->id }}" class="job_checkbox" name="jobs_to_delete[]" value="{{ $job->id }}">                                
                            @endif                           
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="text-align: right">
                <button type="submit" onclick="delete_jobs()" style="float: right; margin:0px 0px 10px 0px">Delete selected jobs <span class="glyphicon glyphicon-remove delete_icon"></span></button>
            </div>
        </div>                               
        <form name="jobs_deletion_form" action="{{ url('job/delete_many') }}" method="post">
            <input id="jobIdList" type="hidden" name="jobs_for_deletion" value="">
        </form>
    </div>
    <div class="col-sm-6">
        
        <div class="panel panel-default" style="margin-top: 20px">
            <div class="panel-heading"  id="help-panel-heading">
                <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> 
                <strong>Help</strong>    
                <div class="help-glyphicon">
                    <span class="glyphicon glyphicon-chevron-down" style="color: gray" aria-hidden="true"></span>
                </div>
            </div>
            <div class="panel-body" style="display: none; background-color: #F2F3F9" id="help-panel-body">
                <img src='{{ asset('images/goal.png') }}' style='display: inline; width:17px; margin-right: 5px'> {{ link_to('files/Genetics_services_manual.pdf','About Genetics vLab') }}
                <br><br>
                <img src='{{ asset('images/man.png') }}' style='display: inline; width:17px; margin-right: 5px'> <a href="http://qiime.org/tutorials/index.html">Qiime Tutorial</a>
                <br><br>
                {{ Form::open(array('url'=>'workspace/add_example_data','name'=>'addExampleData')) }}
                    <img src='{{ asset('images/files.png') }}' style='display: inline; width:17px; margin-right: 5px'>
                    <label onclick="javascript:document.addExampleData.submit();" class="linkStyle">Add example data to your workspace</label>
                {{ Form::close() }}                
            </div>
        </div>
        
        <div class="panel panel-default" style="margin-top: 20px">
            <div class="panel-heading">
                <span class="glyphicon glyphicon-edit" aria-hidden="true"></span> 
                <strong>Submit a new Job</strong>                    
            </div>
            <div class="panel-body" style="background-color: #F2F3F9">                

                <div class="container" style="width: 100%">                                                                                                                 
                    {{ $form }}
                </div>
            </div>
        </div>                        

        <script type="text/javascript">           
            
            $('.change_column_icon').on('click',function(){
                if($('th.job_size_column').is(":hidden")){
                    $(".job_date_column").hide();
                    $(".job_size_column").show();
                } else {
                    $(".job_size_column").hide();
                    $(".job_date_column").show();
                }
            });
            
            // we need this to preserve the checked checkboxes during job list refreshing
            var jobs_to_delete = new Array();
          
            function delete_jobs(){
                var selected_jobs = "";
                var counter = 0;
                
                // Get all selected jobs for deletion
                $(".job_checkbox").each(function(index1,value1){
                   if($(this).is(":checked")){
                        selected_jobs = selected_jobs+";"+$(this).val();
                        counter++;
                   }
                });

                // Check if at least one was selected
                if(counter > 0){
                    // trim initial ';'
                    selected_jobs = selected_jobs.substr(1);
                    $('#jobIdList').val(selected_jobs);
                    document.jobs_deletion_form.submit();
                }
            }
            
            $(document).ready(function(){
                // when a new job is selected/unselected for deletion
                // (for the list we use a variable that is not affected by ajax refreshing)
                 $("#jobListTable").on('click','.job_checkbox',function(){
                     // get job id
                     var checkboxId = $(this).attr('id');                     
                     if($(this).is(":checked")){
                         // add the job id to the list
                         jobs_to_delete.push(checkboxId);
                     } else {
                         // remove the job id from the list
                         jobIndex = jobs_to_delete.indexOf(checkboxId);
                         if (jobIndex > -1) {
                            jobs_to_delete.splice(jobIndex, 1);
                        }
                     }                    
                });
                
                // Enable the bootstrap popovers
                $('[data-toggle="popover"]').popover();                                   
                
            });

            (function job_refresher() {
                $.ajax({
                    url: '{{ url("get_user_jobs") }}', 
                    type: "GET",
                    dataType : 'json',
                    success: function(data) {
                        $('#recent-jobs-wrapper table tbody').empty();
                        var tableString = '';
                        var jobList = JSON.parse(data);
                        if(jobList.length == 0){
                            tableString = "<tr><td colspan='4'>No job submitted recently</td></tr>";
                        }
                        for(var i = 0; i < jobList.length; i++) {
                            var job = jobList[i];
                            tableString = tableString+"<tr>";
                            tableString = tableString+"<td><a href='"+"{{ url('job/"+job.id+"') }}"+"'>Job"+job.id+"</a></td>";
                            tableString = tableString+"<td style='text-align:center'>";
                            switch(job.status){
                                case 'creating':
                                    tableString = tableString+"<div class='job_status status_creating'>Creating...</div>";
                                    break;
                                case 'submitted':
                                    tableString = tableString+"<div class='job_status status_submitted'>Submitted</div>";
                                    break;
                                case 'queued':
                                    tableString = tableString+"<div class='job_status status_queued'>Queued</div>";
                                    break;
                                case 'running':
                                    tableString = tableString+"<div class='job_status status_queued'>Running</div>";
                                    break;
                                case 'completed':
                                    tableString = tableString+"<div class='job_status status_completed'>Completed</div>";
                                    break;
                                case 'failed':
                                    tableString = tableString+"<div class='job_status status_failed'>Failed</div>";
                                    break;
                            }
                            tableString = tableString+"</td>";
                            if($("th.job_date_column").is(":hidden")){
                                tableString = tableString+"<td class='job_date_column' style='display:none'>"+job.submitted_at+"</td>";
                            } else {
                                tableString = tableString+"<td class='job_date_column'>"+job.submitted_at+"</td>";
                            }
                            if($("th.job_size_column").is(":hidden")){
                                tableString = tableString+"<td class='job_size_column' style='display:none'>";
                            } else {
                                tableString = tableString+"<td class='job_size_column'>";
                            }                            

                            if(job.jobsize > 1000000){
                                jobsizeText = (job.jobsize/1000000).toFixed(2) +" GB";
                            } else if(job.jobsize > 1000) {
                                jobsizeText = (job.jobsize/1000).toFixed(1) +" MB";
                            } else {
                                jobsizeText = job.jobsize+" KB";
                            }
                            tableString = tableString+jobsizeText+"</td>";
                            tableString = tableString+"<td>";                          
                            if((job.status == 'creating')||(job.status == 'completed')||(job.status == 'failed')){
                                    action = "{{ url('job/delete') }}";
                                    tableString = tableString+"<input type='checkbox' id='job_checkbox_"+job.id+"' class='job_checkbox' name='jobs_to_delete' value='"+job.id+"'>"; 
                            }                            
                            tableString = tableString+"</td>";
                            tableString = tableString+"</tr>";
                        }
                        $('#recent-jobs-wrapper table tbody').html(tableString);
                    },
                    complete: function() {
                        // Schedule the next request when the current one's complete
                        setTimeout(job_refresher,{{ $refresh_rate }});
                    },
                    error: function(jqXHR, textStatus, errorThrown) 
                    {
                        toastr.error("Job status could not be refreshed automatically.");
                    }
                });
            })();

            $("#selected_function").change(function(){                
               var old_function = selected_function;      
               // Hide the previous form
               var old_form_name = old_function+"_form";
               $("#"+old_form_name).hide();
               // Show the new form
               selected_function = $(this).val();                
               var form_name = selected_function+"_form";
               $("#"+form_name).show();
               
               //
               $('#documentation_icon').attr('title',selected_function+' documentation');
               $('#documentation_link').attr('href',documentation_links[selected_function]);
               
            });

            $("input[type='file']").change(function(){
                ul = $(this).parent().parent().parent().find('ul');
                ul.empty();

                for(var i=0; i< this.files.length; i++){
                   var file = this.files[i];
                   name = file.name.toLowerCase();
                   size = file.size;
                   type = file.type;
                   ul.append("<li><span class='glyphicon glyphicon-file'></span> "+name+"</li>");
                }
             });
             
             $('.workspace-glyphicon').click(function(){
                $('#workspace-panel-body').slideToggle('slow',function(){
                    
                    // Determine the current tab status
                    var status = "";
                    if($('#workspace-panel-body').is(":hidden")){
                        status = "closed";
                    } else {
                        status = "open";
                    }
                    
                    // Store the tab status in session
                    var postData = { 
                        new_status: status
                    };

                    $.ajax(
                    {
                        url : "{{ url('workspace/tab_status') }}",
                        type: "POST",
                        data : postData,
                        success:function(data, textStatus, jqXHR) 
                        {
                           
                        },
                        error: function(jqXHR, textStatus, errorThrown) 
                        {                            
  y
                        }
                    });
                });
                $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');               
             });
             
             $('.help-glyphicon').click(function(){
                $('#help-panel-body').slideToggle();
                $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
             });
             
        </script>
    </div>
</div>


        

