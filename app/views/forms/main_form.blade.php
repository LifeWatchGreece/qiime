{{ Form::open(array('url'=>'job','class'=>'form-horizontal')) }}        

<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('box','Select FASTA sequences from loaded files',$tooltips,$workspace_files) }}     
    {{ form_radio_files('box2','Select mapping file',$tooltips,$workspace_files) }}        
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_checkbox('Demultiplex','Check to demultiplex data','Demultiplex',true,$tooltips) }}   
    {{ form_checkbox('Denoise','Check to denoise data','Denoise',false,$tooltips) }}   
    
    <div style="color: #96403D; margin-left: 20px; margin-top: 10px">split_libraries.py (Demultiplex and quality filter reads)</div>
    
    {{ form_textinput('min_seq_length','-l, --min_seq_length','200','','60',$tooltips) }}
    {{ form_textinput('max_seq_length','-L, --max_seq_length','1000','','60',$tooltips) }}
    {{ form_textinput('barcode_type','-b, --barcode_type','golay_12','','120',$tooltips) }}
    
    <div style="color: #96403D; margin-left: 20px; margin-top: 10px">denoiser.py (Denoise Seqs with Denoiser) & inflate_denoiser_output.py (Inflate denoiser output)</div>
    
    {{ form_textinput('primer','--primer','CATGCTGCCTCCCGTAGGAGT','disabled','250',$tooltips) }}
    
    <div style="color: #96403D; margin-left: 20px; margin-top: 10px">pick_otus.py (Pick Operational Taxonomic Units)</div>
    
    {{ form_dropdown('otu_picking_method','-m, --otu_picking_method',array('uclust','mothur','trie','uclust_ref','usearch','usearch_ref','blast','usearch61','usearch61_ref','sumaclust','swarm','prefix_suffix','cdhit'),'uclast',$tooltips) }} 
    
    <div style="color: #96403D; margin-left: 20px; margin-top: 10px">assign_taxonomy.py (Assigning Taxonomy)</div>
    
    {{ form_textinput('reference_seqs_fp','-r, --reference_seqs_fp','','disabled','',$tooltips) }}
    {{ form_dropdown('assignment_method','-m, --assignment_method',array('uclust','mothur','rdp','rtax','blast'),'uclust',$tooltips) }} 
    {{ form_textinput('blast_db','-b, --blast_db','','disabled','',$tooltips) }}     
        
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">
    $("input[name='Demultiplex']").on('change',function(){
        if($(this).is(':checked')){
            $("input[name='min_seq_length']").prop('disabled','');
            $("input[name='max_seq_length']").prop('disabled','');
            $("input[name='barcode_type']").prop('disabled','');
        } else {
            $("input[name='min_seq_length']").prop('disabled','disabled');
            $("input[name='max_seq_length']").prop('disabled','disabled');
            $("input[name='barcode_type']").prop('disabled','disabled');
        }        
    });
    $("input[name='Denoise']").on('change',function(){        
        if($(this).is(':checked')){
            $("input[name='primer']").prop('disabled','');
        } else {
            $("input[name='primer']").prop('disabled','disabled');            
        }
    });
</script>