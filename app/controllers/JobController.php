<?php

/**
 * Implements functionality related to job submission, job status refreshing and building the results page.
 *
 * @license MIT
 * @author Alexandros Gougousis
 * @author Anastasis Oulas
 */
class JobController extends AuthController {

    private $workspace_path;
    private $jobs_path; 
    private $remote_jobs_path;
    private $remote_workspace_path;
    
    public function __construct() {        
        
        parent::__construct();
        $this->workspace_path = Config::get('qiime.workspace_path');
        $this->jobs_path = Config::get('qiime.jobs_path');
        $this->remote_jobs_path = Config::get('qiime.remote_jobs_path');
        $this->remote_workspace_path = Config::get('qiime.remote_workspace_path');      
        
        // Check if cluster storage has been mounted to web server
        if(!$this->check_storage()){          
            if($this->is_mobile){          
                $response = array('message','Storage not found');
                return Response::json($response,500);
                die();
            } else {
                echo $this->load_view('errors/unmounted','Storage not found');
                die();
            }               
        }
        
        $this->check_registration();        
    }       
    
    /**
     * Builds an HTML page with plots that will be included in the job results page
     * 
     * @param int $job_id
     * @param string $page
     * @return View
     */
    public function job_results_html($job_id,$page){
        $user_email = $this->user_status['email']; 
        $job_record = DB::table('jobs')->where('user_email',$user_email)->where('id',$job_id)->first();
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        
        $data['job_id'] = $job_id;
        $data['image_url_base'] = url('storage/get_job_file/job/'.$job_id);

        // NOTE: the URL fixing that happens in switch block should be executed only once for each file.
        // As it is now, we execute it every time the user accesses the file. Of course, no URL changes
        // take place after the first time, since the URLs have already been fixed the first time.
        
        switch($page){
            case 'rarefraction':                
                $data['use_iframe'] = false;                
                $data['include_path'] = $job_folder."/wf_arare/alpha_rarefaction_plots/rarefaction_plots.html";                
                $title = "Rarefraction plots";
                break;
            case 'bar':
                $data['use_iframe'] = false;                
                $data['include_path'] = $job_folder."/wf_taxa_summary/taxa_summary_plots/bar_charts.html";  
                $title = "Taxa Summary plots (bar)";
                break;
            case 'pie':
                $data['use_iframe'] = false;                
                $data['include_path'] = $job_folder."/wf_taxa_summary/taxa_summary_plots/pie_charts.html";
                $title = "Taxa Summary plots (pie)";
                break;
            case 'area':
                $data['use_iframe'] = false;                
                $data['include_path'] = $job_folder."/wf_taxa_summary/taxa_summary_plots/area_charts.html";
                $title = "Taxa Summary plots (area)";
                break;
            case 'diversity':
                // The diagram that is produced by this page has some strange behaviour (the diagram size
                // increases indefinitely by javascript) so i was forced to use an iframe. 
                $data['use_iframe'] = true;
                $data['iframe_src'] = $data['image_url_base']."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot/index.html";
                $title = "Beta-diversity plots";
                break;
            case 'diversity2':                
                $data['use_iframe'] = false;
                $logs = glob("$job_folder/wf_bdiv_even1820/log*.txt");
                $logs = array_combine($logs, array_map("filemtime", $logs));
                arsort($logs);
                $data['include_path'] = key($logs);
                $title = "Beta-diversity plots";
                break;
        }        
        
        // In case job id wasn't found
        if(empty($job_record)){
            Session::flash('toastr',array('error','You have not submitted recently any job with such ID!'));
            if($this->is_mobile){
                $response = array('message','You have not submitted recently any job with such ID!');
                return Response::json($response,400);
            } else {
                return Redirect::back();
            }            
        }
        
        return $this->load_view('results/html',$title,$data);
    }    
    
    /**
     * 
     * Deletes jobs selected by user
     * 
     * @return RedirectResponse
     */
    public function delete_many_jobs(){
        
        $form = Input::all();
        
        if(!empty($form['jobs_for_deletion'])){
            $job_list_string = $form['jobs_for_deletion'];
            $job_list = explode(';',$job_list_string);

            $total_success = true;
            $error_messages = array();
            $count_deleted = 0;

            foreach($job_list  as $job_id){
                $result = $this->delete_one_job($job_id);
                if($result['deleted']){
                    $count_deleted++;
                } else {
                    $total_success = false;
                    $error_messages[] = $result['message'];
                }
            }

            $deletion_info = array(
                'total'     =>  count($job_list),
                'deleted'   =>  $count_deleted,
                'messages'  =>  $error_messages
            );        

            if($this->is_mobile){            
                return Response::json($deletion_info,200);
            } else {
                return Redirect::to('/')->with('deletion_info',$deletion_info);
            }
        } else {
            return Redirect::to('/');
        }
                          
    }
    
    /**
     * Deletes a specific job
     * 
     * @param int $job_id
     * @return array
     */
    private function delete_one_job($job_id){
        
        $job = Job::find($job_id);
        $user_email = $this->user_status['email'];  
        
        // Check if this job exists
        if(empty($job)){
            $this->log_event("User tried to delete a job that does not exist.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that does not exist'
            );            
        }
        
        // Check if this job belongs to this user
        if($job->user_email != $user_email){
            $this->log_event("User tried to delete a job that does not belong to him.","unauthorized");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job that does not belong to you.'
            ); 
        }        
        
        // Check if the job has finished running
        if(in_array($job->status,array('running','queued','submitted'))){
            $this->log_event("User tried to delete a job that is not finished.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that is not finished.'
            );  
        }        
        
        try {
            // Delete job record
            Job::where('id',$job_id)->delete();

            // Delete job files
            $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
            if(!delete_folder($job_folder)){
                $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                return array(
                    'deleted'   =>  false,
                    'message'   =>  'Unexpected error occured during job folder deletion ('.$job_id.').'
                );                   
            }
            
            return array(
                'deleted'   =>  true,
                'message'   =>  ''
            ); 
        } catch (Exception $ex) {
            $this->log_event("Error occured during deletion of job".$job_id.". Message: ".$ex->getMessage(),"error");
            return array(
                'deleted'   =>  false,
                'message'   =>  'Unexpected error occured during deletion of a job ('.$job_id.').'
            ); 
        }
                        
    }
    
    /**
     * Retrieves the list of jobs in user's workspace
     * 
     * @return JSON
     */
    public function get_user_jobs(){
        
        $user_email = $this->user_status['email']; 
        $job_list = Job::where('user_email',$user_email)->orderBy('id','desc')->get()->toJson();
        return Response::json($job_list,200);
                
    }
    
    /**
     * Retrieves the status of a submitted job
     * 
     * @param int $job_id
     * @return JSON
     */
    public function get_job_status($job_id){
        
        $user_email = $this->user_status['email']; 
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(empty($result)){
            $this->log_event("Trying to retrieve status for a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve status for a job that does not belong to this user!');
            return Response::json($response,401);
        }
        
        return Response::json(array('status' => $result->status),200);
        
    }
    
    /**
     * Retrieves the R script used in the execution of a submitted job.
     * 
     * @param int $job_id
     * @return JSON
     */
    public function get_qiime_script($job_id){
        $user_email = $this->user_status['email']; 
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $fullpath = $job_folder.'/Qiime.txt';
        
        // Check if the R script exists
        if(!file_exists($fullpath)){
            $this->log_event("Trying to retrieve non existent Qiime script.","illegal");
            $response = array('message','Trying to retrieve non existent Qiime script!');
            return Response::json($response,400);
        }
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(empty($result)){
            $this->log_event("Trying to retrieve an Qiime script from a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve an Qiime script from a job that does not belong to this user!');
            return Response::json($response,401);
        }
        
        $r = file($fullpath);
        return Response::json($r,200);
        
        
    }
    
    /**
     * Retrieves a file from a job's folder after it identifies the existence of a path before the file name
     * 
     * @param int $job_id
     * @param string $path
     * @param string $filename
     * @return file|View|JSON
     */
    public function get_job_filepath($job_id,$path,$filename){
        if(!empty($path)){
            $user_email = $this->user_status['email']; 
            $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;           
            $parts = str_replace(";","/",$path);
            $job_folder = $job_folder."/".$parts;            
            return $this->get_job_file($job_id, $filename, $job_folder);
        } else {
            return $this->get_job_file($job_id, $filename);
        }
    }
    
    /**
     * Retrieves a file from a job's folder.
     * 
     * @param int $job_id
     * @param string $filename
     * @return View|file|JSON
     */
    public function get_job_file($job_id,$filename,$deep_job_folder=''){
        
        $user_email = $this->user_status['email']; 
        if(!empty($deep_job_folder)){
            $job_folder = $deep_job_folder;
        } else {            
            $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        }        
        
        $fullpath = $job_folder.'/'.$filename;

        if(!file_exists($fullpath)){           
            $this->log_event("Trying to retrieve non existent file: ".$fullpath,"illegal");
            if($this->is_mobile){
                $response = array('message','Trying to retrieve non existent file!');
                return Response::json($response,400);
            } else {
                return $this->illegalAction();
            }     
        }
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(!empty($result)){              
            $parts = pathinfo($filename);
            $new_filename = $parts['filename'].'_job'.$job_id.'.'.$parts['extension'];
                         
            switch($parts['extension']){
                case 'png':                    
                    header("Content-Type: image/png");
                    readfile($fullpath);                         
                    exit;
                    break;
                case 'pdf':
                case 'csv':
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.$new_filename);
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($fullpath));
                    readfile($fullpath);
                    exit;
                    break;
                case 'css':
                    header("Content-Type: text/css");                    
                    readfile($fullpath);                         
                    exit;
                    break;
                case 'html':
                    header("Content-Type: text/html");                    
                    readfile($fullpath);                         
                    exit;
                    break;
                case 'txt':
                    header("Content-Type: text/plain");                    
                    readfile($fullpath);                         
                    exit;
                    break;
                case 'js':
                    $js_data = file_get_contents($fullpath);
                    echo $js_data;
                    exit;
                    break;
            }
            
        } else {
            $this->log_event("Trying to retrieve a file that does not belong to a user's job.","unauthorized");
            if($this->is_mobile){
                $response = array('message',"Trying to retrieve a file that does not belong to a user's job");
                return Response::json($response,401);
            } else {
                return $this->unauthorizedAccess();
            }               
        }  
        
    }
    
    /**
     * 
     * @param int $job_idRefreshes the status of a specific job
     * 
     * @param int $job_id
     * @return void
     */
    private function refrest_single_status($job_id){
        $job = Job::find($job_id);
        
        $job_folder = $this->jobs_path.'/'.$job->user_email.'/job'.$job_id;
        $pbs_filepath = $job_folder.'/job'.$job->id.'.pbs';
        $submitted_filepath = $job_folder.'/job'.$job->id.'.submitted';
            
        if(file_exists($pbs_filepath)){
            $status = 'submitted';
        } else if(!file_exists($submitted_filepath)){
            $status = 'creating';
        } else {
            $this->log_event('Submitted job'.$job_id,'info');
            $status_file = $job_folder.'/job'.$job_id.'.jobstatus';
            $status_info = file($status_file);
            $status_parts = preg_split('/\s+/', $status_info[0]); 
            $status_message = $status_parts[8];
            switch($status_message){
                case 'Q':
                    $status = 'queued';
                    break;
                case 'R':
                    $status = 'running';
                    break;
                case 'ended':
                    $status = 'completed';
                    break;
                case 'ended_PBS_ERROR':
                    $status = 'failed';
                    break;
            }

            $fileToParse = 'Qiime.txt.out';
            
            // If job has run, check for R errors
            if($status == 'completed'){
                $parser = new QimeParser();
                $parser->parse_output($job_folder,$fileToParse);        
                if($parser->hasFailed()){
                    $status = 'failed'; 
                }
            }                 
        }

        $job->status = $status;
        $job->save();
        
        // IF job was completed successfully use it for statistics
        if($status == 'completed'){
            $job_log = new JobsLog();
            $job_log->id = $job->id;
            $job_log->user_email = $job->user_email;
            $job_log->status = $job->status;
            $job_log->submitted_at = $job->submitted_at;
            $job_log->started_at = $job->started_at;
            $job_log->completed_at = $job->completed_at;
            $job_log->jobsize = $job->jobsize;
            $job_log->inputs = $job->inputs;
            $job_log->save();
        }
    }        
    
    /**
     * Displays the results page of a job
     * 
     * @param int $job_id
     * @return View|JSON
     */
    public function job_page($job_id){        
        $user_email = $this->user_status['email']; 
        $job_record = DB::table('jobs')->where('user_email',$user_email)->where('id',$job_id)->first();
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $data['job'] = $job_record;
        
        // In case job id wasn't found
        if(empty($job_record)){
            Session::flash('toastr',array('error','You have not submitted recently any job with such ID!'));
            if($this->is_mobile){
                $response = array('message','You have not submitted recently any job with such ID!');
                return Response::json($response,400);
            } else {
                return Redirect::back();
            }            
        }
        
        // Load information about input files 
        $inputs = array();
        $input_files = explode(';',$job_record->inputs);        
        foreach($input_files as $ifile){
            $info = explode(':',$ifile);
            $id = $info[0];
            $filename = $info[1];
            $record = WorkspaceFile::where('id',$id)->first();
            if(empty($record)){
                $exists = false;
            } else {
                $exists = true;
            }
            $inputs[] = array(
                'id'    =>  $id,
                'filename'  =>  $filename,
                'exists'    =>  $exists
            );
        }        
        
        if(in_array($job_record->status,array('submitted','running','queued'))){
            $this->refrest_single_status($job_id);
        }        
        
        $fileToParse = 'Qiime.txt.out';
        
        if($job_record->status == 'failed'){            
            $log_file = $job_folder."/job".$job_id.".log";
            $parser = new QimeParser();
            $parser->parse_output($job_folder,$fileToParse);
            if($parser->hasFailed()){
                $data['errorString'] = implode("<br>",$parser->getOutput());
                $data['errorString'] .= $parser->parse_log($log_file);
            } else {
                $data['errorString'] = "Error occured during submission.";
                $data['errorString'] .= $parser->parse_log($log_file);
            }
            if($this->is_mobile){
                $response = array('message','Error occured during submission.');
                return Response::json($response,500);
            } else {
                return $this->load_view('results/failed','Job Results',$data); 
            }              
        }
        
        if(in_array($job_record->status,array('submitted','queued','running'))){
            if($this->is_mobile){
                $response = array('data',$data);
                return Response::json($response,500);
            } else {
                $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
                return $this->load_view('results/submitted','Job Results',$data);  
            }               
        }
                
        // In case job was completed successfully and URLs fixing has be done,
        // do it now.
        if(!$job_record->urls_fixed){
            $file_base_url = url('storage/get_job_file/job/'.$job_id);
            $this->fix_job_urls($job_folder,$file_base_url);
            $job = Job::find($job_id);
            $job->urls_fixed = 1;
            $job->save();
        }
                
        $user_workspace = $this->workspace_path.'/'.$user_email;
        
        // Build the result page for this job     
        return $this->job_results($job_id,$job_folder,$user_workspace,$inputs);
        
    }

    /**
     * Fixing the URLs used by the automatically built results page, so that .js and .css files can be retrieved.
     * 
     * @param string $job_folder
     * @param string $file_base_url
     */
    private function fix_job_urls($job_folder,$file_base_url){

        // URLs used in <script> cannot be changed dynamically by javascript. So, we have to do it in advance using PHP.
        $bar_file = $job_folder."/wf_taxa_summary/taxa_summary_plots/bar_charts.html";
        if(file_exists($bar_file)){
            $contents = file_get_contents($bar_file);
            $new_content = str_replace("=\"./js/overlib.js","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;js/overlib.js",$contents);
            $new_content2 = str_replace("=\"./css/qiime_style.css","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;css/qiime_style.css",$new_content);                               
            file_put_contents($bar_file,$new_content2);
        }

        // URLs used in <script> cannot be changed dynamically by javascript. So, we have to do it in advance using PHP.
        $pie_file = $job_folder."/wf_taxa_summary/taxa_summary_plots/pie_charts.html";
        if(file_exists($pie_file)){
            $contents = file_get_contents($pie_file);
            $new_content = str_replace("=\"./js/overlib.js","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;js/overlib.js",$contents);
            $new_content2 = str_replace("=\"./css/qiime_style.css","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;css/qiime_style.css",$new_content);                               
            file_put_contents($pie_file,$new_content2);
        }

        // URLs used in <script> cannot be changed dynamically by javascript. So, we have to do it in advance using PHP.
        $area_file = $job_folder."/wf_taxa_summary/taxa_summary_plots/area_charts.html";
        if(file_exists($area_file)){
            $contents = file_get_contents($area_file);
            $new_content = str_replace("=\"./js/overlib.js","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;js/overlib.js",$contents);
            $new_content2 = str_replace("=\"./css/qiime_style.css","=\"".$file_base_url."/wf_taxa_summary;taxa_summary_plots;css/qiime_style.css",$new_content);                               
            file_put_contents($area_file,$new_content2);
        }

        // URLs used in <script> cannot be changed dynamically by javascript. So, we have to do it in advance using PHP.
        $beta_diversity_file = $job_folder."/wf_bdiv_even1820/unweighted_unifrac_emperor_pcoa_plot/index.html";
        if(file_exists($beta_diversity_file)){
            $contents = file_get_contents($beta_diversity_file);
            $new_content = str_replace("=\"emperor_required_resources/js/js","=\"".$file_base_url."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot;emperor_required_resources;js;js",$contents);
            $new_content2 = str_replace("=\"emperor_required_resources/js","=\"".$file_base_url."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot;emperor_required_resources;js",$new_content);            
            $new_content3 = str_replace("=\"emperor_required_resources/css","=\"".$file_base_url."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot;emperor_required_resources;css",$new_content2);
            $new_content4 = str_replace("=\"emperor_required_resources/emperor/js","=\"".$file_base_url."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot;emperor_required_resources;emperor;js",$new_content3);
            $new_content5 = str_replace("=\"emperor_required_resources/emperor/css","=\"".$file_base_url."/wf_bdiv_even1820;unweighted_unifrac_emperor_pcoa_plot;emperor_required_resources;emperor;css",$new_content4);
            file_put_contents($beta_diversity_file,$new_content5);
        }
       
    }
    
    /**
     * Submits a new job
     * Handles the basic functionlity of submission that is not related to script building and execution
     * 
     * @return RedirectResponse|JSON
     */
    public function submit(){        
        try {
            $form = Input::all();        
            $user_email = $this->user_status['email'];                        

            // Validation
            if(empty($form['box'])) {
                if($this->is_mobile){
                    $response = array('message','You forgot to select an input file!');
                    return Response::json($response,400);
                } else {
                    Session::flash('toastr',array('error','You forgot to select an input file!'));
                    return Redirect::back();
                }               
            } else {
                $box= $form['box'];
            }
        
        } catch(Exception $ex){
            $this->log_event($ex->getMessage(),"error");
        }
                
        try {
            // Create a job record
            $job = new Job();
            $job->user_email = $user_email;
            $job->status = 'creating';
            $job->submitted_at = date("Y-m-d H:i:s");
            $job->save();

            // Get the job id and create the job folder
            $job_id = 'job'.$job->id;
            $user_jobs_path = $this->jobs_path.'/'.$user_email;
            $job_folder = $user_jobs_path.'/'.$job_id;
            $user_workspace = $this->workspace_path.'/'.$user_email;       
            

            // Create the required folders if they are not exist
            if(!file_exists($user_workspace)){
                if(!mkdir($user_workspace)){
                    $this->log_event('User workspace directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User workspace directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }                      
                }                    
            }
            if(!file_exists($user_jobs_path)){              
                if(!mkdir($user_jobs_path)){
                    $this->log_event('User jobs directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User jobs directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }  
                }
            }
            
            if(!file_exists($job_folder)){
                if(!mkdir($job_folder)){
                    $this->log_event('Job directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','Job directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }
                }
            }
            $remote_job_folder = $this->remote_jobs_path.'/'.$user_email.'/'.$job_id;
            $remote_user_workspace = $this->remote_workspace_path.'/'.$user_email;
            
            // Run the function
            if(is_array($form['box']))
                $inputs = implode(';',$form['box']);                
            else
                $inputs = $form['box'];
            $submitted = $this->submit_job($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,$inputs);
            if(!$submitted){
                
                $this->log_event("Job submission failed!","error");
                //Session::flash('toastr',array('error','New job submission failed!'));
                
                // Delete the job record
                Job::where('id',$job->id)->delete();
               
                 // Delete folder if created
                if(file_exists($job_folder)){
                    if(!rmdir_recursive($job_folder)){
                        $this->log_event('Folder '.$job_folder.' could not be deleted after failed job submission!',"error");
                    }
                }
                
                if($this->is_mobile){
                    $response = array('message','New job submission failed!');
                    return Response::json($response,500);
                } else {
                    return Redirect::back();
                }
            }
            
            $input_ids = array();
            $inputs_list = explode(';',$inputs);
            foreach($inputs_list as $input){
                $file_record = WorkspaceFile::whereRaw('BINARY filename LIKE ?',array($input))->first();
                $input_ids[] = $file_record->id.":".$input;
            }
            $input_ids_string = implode(';',$input_ids);
            
            
            $job->status = 'submitted';
            $job->jobsize = directory_size($job_folder);
            $job->inputs = $input_ids_string;
            $job->save();                        
            
        } catch (Exception $ex) { 
            // Delete record if created
            if(!empty($job_id)){
                $job->delete();
            }            
            // Delete folder if created
            if(file_exists($job_folder)){
                if(!rmdir_recursive($job_folder)){
                    $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                }
            }
            
            $this->log_event($ex->getMessage(),"error");
            Session::flash('toastr',array('error','New job submission failed!'));
            if($this->is_mobile){
                $response = array('message','New job submission failed!');
                return Response::json($response,500);
            } else {
                return Redirect::back();
            }
        }
            
        Session::flash('toastr',array('success','The job submitted successfully!'));
        //$this->log_event("New job submission","info");
        if($this->is_mobile){
            return Response::json(array(),200);
        } else {
             return Redirect::to('/');
        }       
        
    }

    /**
     * Handles the part of job submission functionlity that relates to script building and execution.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function submit_job($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        if(empty($form['primer'])){
            $primer = '';
        } else {
            $primer = $form['primer'];
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];  
        $inputs .= ";".$box2;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }        
        
        if(!empty($form['Demultiplex'])){
            $demultiplex = $form['Demultiplex'];
        } else {
            $demultiplex = null;
        }      
        if(!empty($form['Denoise'])){
            $denoise = $form['Denoise'];
        } else {
            $denoise = null;
        } 
        
        // Build Qiime.txt
        if (!($fh = fopen("$job_folder/Qiime.txt", "w"))) 
                exit("Unable to open file $job_folder/Qiime.txt");           
            
        fwrite($fh, "/usr/bin/validate_mapping_file.py -m $remote_job_folder/$box2 -o $remote_job_folder/mapping_output\n");
        
        if($demultiplex =="Demultiplex"){
             fwrite($fh, "/usr/bin/split_libraries.py -m $remote_job_folder/$box2 -f $remote_job_folder/$box -o $remote_job_folder/split_library_output -b 10 -z truncate_only;\n");
        } else {
            system("mkdir $job_folder/split_library_output");
            system("mv $job_folder/$box $job_folder/split_library_output/seqs.fna");
        }
        
        if($denoise =="Denoise"){
             fwrite($fh, "/usr/bin/denoise_wrapper.py -v -i $remote_job_folder/$box -f $remote_job_folder/split_library_output/seqs.fna -o $remote_job_folder/denoised_output -m $job_folder/$box2;\n");
             system("mv $job_folder/denoised_output/denoised_seqs.fasta $job_folder/split_library_output/seqs.fna");
        }

            // Build the parameters file
            if (!($fh_param = fopen("$job_folder/Qiime_parameters.txt", "w"))) 
                exit("Unable to open file $job_folder/Qiime_parameters.txt");

            fwrite($fh_param, "split_libraries:min_seq_length ".$form['min_seq_length']."\n");
            fwrite($fh_param, "split_libraries:max_seq_length ".$form['max_seq_length']."\n");
            fwrite($fh_param, "split_libraries:barcode_type ".$form['barcode_type']."\n");
            fwrite($fh_param, "denoiser:primer ".$primer."\n");
            fwrite($fh_param, "pick_otus:otu_picking_method ".$form['otu_picking_method']."\n");
            fwrite($fh_param, "align_seqs:template_fp /mnt/big/Metagenomics/core_set_aligned.fasta.imputed\n");
            fwrite($fh_param, "plot_taxa_summary:chart_type area,bar,pie\n");
            fwrite($fh_param, "assign_taxonomy:id_to_taxonomy_fp /mnt/big/Metagenomics/qiime-1.8.0/gg_otus-13_8-release/taxonomy/97_otu_taxonomy.txt\n");
            fwrite($fh_param, "assign_taxonomy:assignment_method ".$form['assignment_method']."\n");
            fwrite($fh_param, "assign_taxonomy:blast_db None\n");
            fwrite($fh_param, "assign_taxonomy:reference_seqs_fp /mnt/big/Metagenomics/qiime-1.8.0/gg_otus-13_8-release/rep_set/97_otus.fasta\n");
            fwrite($fh_param, "pynast:template_alignment_fp    /mnt/big/Metagenomics/qiime-1.8.0/core_set_aligned.fasta.imputed\n");

            fclose($fh_param);
            
        fwrite($fh, "/usr/bin/pick_de_novo_otus.py -f -p $remote_job_folder/Qiime_parameters.txt -i $remote_job_folder/split_library_output/seqs.fna -o $remote_job_folder/otus;\n");
        fwrite($fh, "/usr/bin/summarize_taxa_through_plots.py -f -p $remote_job_folder/Qiime_parameters.txt -i $remote_job_folder/otus/otu_table.biom -o $remote_job_folder/wf_taxa_summary -m $remote_job_folder/$box2;\n");
        fwrite($fh, "/usr/bin/alpha_rarefaction.py -f -p $remote_job_folder/Qiime_parameters.txt -i $remote_job_folder/otus/otu_table.biom -m $remote_job_folder/$box2 -o $remote_job_folder/wf_arare -t $remote_job_folder/otus/rep_set.tre;\n");
        fwrite($fh, "/usr/bin/beta_diversity_through_plots.py -f -p $remote_job_folder/Qiime_parameters.txt -i $remote_job_folder/otus/otu_table.biom -m $remote_job_folder/$box2 -o $remote_job_folder/wf_bdiv_even1820 -t $remote_job_folder/otus/rep_set.tre -e 1820;\n");
        fwrite($fh, "/usr/bin/make_otu_heatmap.py -i $remote_job_folder/otus/otu_table.biom -o $remote_job_folder/otu_table_heatmap.pdf -m $remote_job_folder/$box2;\n");
        fwrite($fh, "/usr/bin/upgma_cluster.py -i $remote_job_folder/wf_bdiv_even1820/weighted_unifrac_dm.txt -o $remote_job_folder/upgma.tre;\n");
        fwrite($fh, "/usr/bin/jackknifed_beta_diversity.py -i $remote_job_folder/otus/otu_table.biom -t $remote_job_folder/otus/rep_set.tre -m $remote_job_folder/$box2 -o $remote_job_folder/jack -e 110;\n");
        fwrite($fh, "/usr/bin/make_bootstrapped_tree.py -m $remote_job_folder/jack/unweighted_unifrac/upgma_cmp/master_tree.tre -s $remote_job_folder/jack/unweighted_unifrac/upgma_cmp/jackknife_support.txt -o $remote_job_folder/jack/unweighted_unifrac/upgma_cmp/jackknife_named_nodes.pdf;\n");
        fwrite($fh, "/usr/bin/make_otu_network.py -i $remote_job_folder/otus/otu_table.biom -m $remote_job_folder/$box2 -o $remote_job_folder/otu_network;\n");
        fwrite($fh, "biom convert -i $remote_job_folder/otus/otu_table.biom -o $remote_job_folder/otu_table.from_biom.txt --to-tsv --header-key taxonomy;\n");        
        fwrite($fh, "tar -czf $remote_job_folder/qiime.tgz -C $remote_job_folder/ otus wf_taxa_summary wf_arare wf_bdiv_even1820 jack otu_network otu_table_heatmap.pdf upgma.tre otu_table.from_biom.txt;\n");

        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -q bigmem\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "sh $remote_job_folder/Qiime.txt > $remote_job_folder/Qiime.txt.out\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);    
        
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");   

        return true;
    }
    
    /**
     * Loads job results information.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function job_results($job_id,$job_folder,$user_workspace,$input_files){
        
        $data = array();
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;        
        
        $parser = new QimeParser();
        $parser->parse_output($job_folder,'Qiime.txt.out');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }                
        }
        
        $data['lines'] = $parser->getOutput();
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
            	
    }
    
}
