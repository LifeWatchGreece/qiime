<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RvlabParser
 *
 * @author Alexandros
 */
class QimeParser {
    
    private $error_string;
    private $output_string;
    private $buffer_string;
    
    public function __construct() {
        $this->error_string = array();
        $this->output_string = array();
    }
    
    public function parse_log($log_file){
        $log_text = "";
        if(file_exists($log_file)){
            $handle = fopen($log_file, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) { 
                    $log_text .= "<br>".$line;
                }
                fclose($handle);
                return $log_text;
            } else {
                return "";
            }
        } else {
            return "";
        }
    }
    
    public function parse_output($job_folder,$filename){
        
        $this->error_string = array();
        $this->output_String = array();
        $this->buffer_string = array();
        
        try {            
            $handle = fopen($job_folder.'/'.$filename, "r");
            $found_nothing = true;
            if ($handle) {
                
                while (($line = fgets($handle)) !== false) { 
                    $this->buffer_string[] = $line;
                    if (strpos($line,'No errors or warnings found in mapping') !== false) {                    
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } else {                       
                        $this->output_string[] = "<br>".$line;                        
                    }                                        
                }
                fclose($handle);
                
                if(file_exists($job_folder."/Qiime.txt.error") ) {
                    $found_nothing = false;
                    $handle2 = fopen($job_folder."/Qiime.txt.error","r");
                    while (($line = fgets($handle2)) !== false) {
                        $this->error_string[] = "<br>".$line;
                    }
                }                                             
            } else {
                $this->error_string[] = "Output of Qiime script could not be opened!";
            } 
        } catch (Exception $ex) {
            $this->error_string[] = "Unexpected error happened when parsing the output of R script.";
            $this->error_string[] = "<br>Error message: ".$ex->getMessage();
        }
        
    }
    
    public function hasFailed(){
        if(!empty($this->error_string)){
            return true;
        } else {
            return false;
        }
    }
    
    public function getOutput(){
        if(!empty($this->error_string)){
            return $this->error_string;
        } else {
            return $this->output_string;
        }
    }
    
}
