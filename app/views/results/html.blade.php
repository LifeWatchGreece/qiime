<a href="{{ url('job/'.$job_id) }}" style="margin-left: 30px; margin-bottom: 10px"><span class="glyphicon glyphicon-arrow-left" aria-hidden="true" style="color:#428bca"></span> Back to Home</a>
<br><br>
@if($use_iframe)
    <script language="javascript" type="text/javascript">
        function resizeIframe(obj) {
          obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
        }
    </script>

    <iframe name="Stack" src="{{ $iframe_src }}" style="width: 100%; min-height: 650px; border: none" scrolling="no" id="iframe" onload='javascript:resizeIframe(this);' />    
@else
    <?php include($include_path); ?>

    <!-- The need for this javascript block is raised by the fact that some images (not sure about links)
        maybe be created dynamically. So, the URL fixing cannot happen exclusively in advance. -->
    <script type="text/javascript">

        var image_url_base = "{{ $image_url_base }}";

        $(document).ready(function(){   

            // Fix images and links that exist when the page loads
            fixImages($(this));                      
            fixLinks($(this));

            // Some images of the Rarefaction plots page are produced dynamically 
            // and placed inside a div with id=plots. So, when the contents of this
            // div change, we need to check for images that need fixing.
            $('#plots').bind("DOMNodeInserted",function(){   
                fixImages($(this));
            });

            // Changes the URLs of image src property. These URLs were produced
            // automatically by Qiime. We need to replace them with URLs supported
            // by our application.
            function fixImages(element){
                element.find('img').each(function(index){               
                    var src = $(this).prop('src');                
                    if ((src.indexOf('qiime') !== -1)&&(src.indexOf('charts') !== -1)) { // Detecting URLs relative to chart images
                        // is a chart image url
                        var startPos = src.indexOf('charts');
                        var relativeUrl = src.substring(startPos);
                        $(this).prop('src',image_url_base+'/wf_taxa_summary;taxa_summary_plots;'+relativeUrl);
                    } else if (src.indexOf('average_plots') !== -1) { // Detecting URLs relative to Rarefaction plots
                        // is a chart image url
                        var startPos = src.indexOf('average_plots');
                        var relativeUrl = src.substring(startPos);
                        $(this).prop('src',image_url_base+'/wf_arare;alpha_rarefaction_plots;'+relativeUrl);
                    }
                });
            }

            // What we did for images in fixImages(), we need to do it for href
            // property of links (they are used mostly for pdf files)
            function fixLinks(element){
                element.find('a').each(function(index){
                    var href = $(this).prop('href');
                    if ((href.indexOf('qiime') !== -1)&&(href.indexOf('charts') !== -1)) {
                        // is a chart image url
                        var startPos = href.indexOf('charts');
                        var relativeUrl = href.substring(startPos);
                        $(this).prop('href',image_url_base+'/wf_taxa_summary;taxa_summary_plots;'+relativeUrl);
                    } else if((href.indexOf('qiime') !== -1)&&(href.indexOf('raw_data') !== -1)){
                        var startPos = href.indexOf('raw_data');
                        var relativeUrl = href.substring(startPos);
                        $(this).prop('href',image_url_base+'/wf_taxa_summary;taxa_summary_plots;'+relativeUrl);
                    }
                });
            }

        });
    </script>
    
@endif 