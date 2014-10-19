<?php

class GifTool
{
    protected $scale = '-vf scale="320:trunc(ow/a/2)*2"';
    protected $scaleGif = '-vf scale="400:trunc(ow/a/2)*2"';

	function help (){
		echo "Usage: berlioz video_file operation";
		return 0;
	}



	function parse_video_info (){
		$cmd = $this->ffprobe." -print_format json -show_format -show_streams ".$this->videos_source.$this->source;
		$string = exec($cmd, $output, $exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");
		$json_raw = implode("\n", $output);
		$this->infos = json_decode($json_raw, true);
	}

	function to_thumbnails (){
		if(! is_dir($this->video_thumbnails_path)){
			mkdir($this->video_thumbnails_path,0775,true);
			if(! is_dir($this->video_thumbnails_path))
				throw new Exception("could not create thumbnail path $this->video_thumbnails_path for video $this->infos['format']['filename']");
		}
		$cmd = $this->ffmpeg.$this->verbose." -i ".$this->infos['format']['filename']." -f image2 -threads 0 {$this->scale} -vf \"select='eq(pict_type,PICT_TYPE_I)'\" -vsync vfr ".$this->video_thumbnails_path."thumb%04d.jpg";
		exec ($cmd,$output,$exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");

	}

	function to_gif($start=25.000,$stop=30.000,$text="",$quality='low') {
        $id = sha1(json_encode(compact('start', 'stop', 'text', 'quality')));
        $scale = '';
        if(file_exists($this->video_gifs_path.$id.'.gif'))
            return $this->video_gifs_path.$id.'.gif';

		if($stop < $start)
			throw new Exception("Stop point is prior to start point");
		$overlay = "";
		if($text != ""){
			$this->transparent_frame($text);
			$overlay = " -i ".$this->videos_path.$this->basename_video."/transparent.png -filter_complex 'overlay=0:0' ";
		}
		$duration = $stop-$start;
		if($quality === 'low'){
			$cmd=$this->ffmpeg.$this->verbose." -ss ".$start."  -i ".$this->videos_source.$this->source." ".$overlay." ".$scale." -t ".$duration." -r 10 ".$this->video_gifs_path.$id.".gif";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
		}
		elseif($quality === 'medium'){
            $scale = $this->scaleGif;
			if(!is_dir($this->video_frames_path.$id)){
				mkdir($this->video_frames_path.$id,0775,true);
				if(!is_dir($this->video_frames_path.$id))
					throw new Exception("Error Processing Request", 1);
			}
			$cmd = $this->ffmpeg.$this->verbose." -ss ".$start." -i ".$this->videos_source.$this->source." ".$scale." -t ".$duration." -r 25 ".$this->video_frames_path.$id.".gif";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
/*			$cmd = "convert -delay 5 -loop 0 ".$this->video_frames_path.$id."/fout*.jpg ".$this->video_gifs_path.$id.".gif";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);*/
			if($text){
				$this->transparent_frame($text);
				$watermark = $this->videos_path.$this->basename_video."/transparent.png";
				$watermarked_animation = $this->video_gifs_path.$id.".gif";
				$cmd = " -delay 5 -loop 0 ".$this->video_gifs_path.$id.".gif"." -coalesce -gravity South "." -geometry +0+0 null: $watermark -layers composite -layers optimize ";
				exec("convert $cmd $watermarked_animation ",$output,$exit); 
				if($exit != 0)
					throw new Exception("Error Processing Request $cmd", 1);
				}
		}

		/*if($quality === 'high'){
			$cmd=$this->ffmpeg.$this->verbose." -ss ".$start." -i ".$this->videos_source.$this->source." -t ".$duration."  -acodec libvorbis -an ".$this->video_mute_path."output.ogg";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
		}*/
		return $this->video_gifs_path.$id.'.gif';
	}

	function transparent_frame($text="",$position="bottom"){
		$image = imagecreatetruecolor($this->infos['streams'][0]['width'], $this->infos['streams'][0]['height']);

		$font = 'arial.ttf';

		$fw = imagefontwidth(5);     // width of a character
		$font_size = 24;
		$l = strlen($text);          // number of characters
		$tw = $l*$font_size;              // text width
		$xpos = ($this->infos['streams'][0]['width'] - $tw)/2;
		$ypos = 30;
		if($position === 'bottom'){
			$ypos = $this->infos['streams'][0]['height'] -$ypos;
		}

		imagealphablending($image, true);
		imagesavealpha($image, true);

		$xi = imagesx($image);
	    $yi = imagesy($image);
		$box = imagettfbbox($font_size,0,$font,$text);	
		$xr = abs(max($box[2], $box[4]));
	    $x = intval(($xi - $xr) / 2);
	    $y = $ypos - 10;
		$text_color = imagecolorallocate($image, 255, 255, 255);
		imagefill($image,0,0,0x7fff0000);

		$white = imagecolorallocate($image, 255,255,255);

		imagettftext($image, $font_size, 0, $x, $y, $white, $font, $text);

		header('content-type: image/png');
		imagepng($image,$this->videos_path.$this->basename_video."/transparent.png");
		imagedestroy($image);
	}

	function mplayer_convert(){
		$cmd = "mplayer -vo jpeg -sstep 10 -endpos ".floor($this->infos['format']['duration'])." ".$this->videos_source.$this->source;

        $cwd = getcwd();
        chdir($this->video_thumbnails_path);

		exec($cmd,$output,$exit);

        chdir($cwd);

		if($exit !== 0)
			throw new Exception("Invalid exit code for: $cmd");
	}

	function to_mute() {
        $dest = sprintf('%smute-%s.mp4', $this->video_mute_path, $this->source);
        if(file_exists($dest))
            unlink($dest);

        $cmd =
            $this->ffmpeg
            . $this->verbose
            . '  -i ' . $this->videos_source.$this->source
            . ' ' . $this->scale . ' -c:v libx264 -crf 20 -an  '
            . "$dest.tmp.mp4"
        ;
		exec($cmd,$output,$exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");

        exec(sprintf('qt-faststart %s %s', escapeshellarg("$dest.tmp.mp4"), escapeshellarg($dest)));
        unlink("$dest.tmp.mp4");
	}

	function get_key_frames(){
		$cmd = $this->ffprobe." {$this->verbose} -show_frames -select_streams v -i ".$this->videos_source.$this->source." -print_format json | "."grep -A6 '\"key_frame\": 1,' | grep best_effort_timestamp_time >".$this->json_key_frames;
		exec($cmd,$output,$exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");

	}

	function link_thumbs_with_key_frames(){
		try{
			if(!file_exists($this->json_key_frames))
				throw new Exception("Error Processing Request", 1);
		}
		catch(Exception $e){
			echo "error $e";
			return 0;
		}

//		$json_raw = implode("\n", $output);

		$myfile = fopen("$this->json_key_frames", "r") or die("Unable to open file!");
		// Output one line until end-of-file
		$i = 1;
		while(!feof($myfile)) {
			$line = fgets($myfile);
			if(preg_match('/best_effort_timestamp_time/',$line)){
				$time = substr($line,43,-10);
				rename($this->video_thumbnails_path.sprintf('thumb%04d.jpg',$i),$this->video_thumbnails_path.$time.'.jpg');
			}
			$i++;
		}
		fclose($myfile);
/*		$content = file_get_contents($this->json_key_frames);
		$frames = json_decode($content, true);
		$i = 0;
		foreach($frames as $frame){
			if($frame['key_frame']){
				rename($this->video_thumbnails_path.sprintf('thumb%04d.jpg',$i),$this->video_thumbnails_path.round($frame['best_effort_timestamp']/100));
			}
		}*/
	}

	public function get_config(){
		$myfile = fopen("config", "r") or die("Unable to open file!");
		while(!feof($myfile)) {
		  list($key,$value) = explode('=',fgets($myfile));
		  if(!empty($key)){
		  	$this->{$key} = trim($value);
		  }
		}
		fclose($myfile);
	}

	public function __construct($source,
								$verbose=1){
		$this->source = $source;

		$this->get_config();
		if(!$verbose)
			$this->verbose = " -v quiet ";
		else
			$this->verbose = "";
	//path to ffmpeg executable
		$this->ffmpeg=$ffmpeg;
echo $this->videos_source.$this->source."\n";
		$this->basename_video=pathinfo($source,PATHINFO_FILENAME);
		try{
			if ( ! file_exists($this->videos_source.$this->source) ) {
				throw new Exception("source file does not exist:".$this->source);
			}
		}
		catch(Exception $e){
			echo $e."\n";
			exit();
		}

		$this->parse_video_info($ffprobe,$source);

		$this->video_thumbnails_path=$this->videos_path.$this->basename_video.'/thumbnails/';
		$this->video_mute_path=$this->videos_path.$this->basename_video.'/mute/';
		$this->video_gifs_path=$this->videos_path.$this->basename_video.'/gifs/';
		$this->video_frames_path=$this->videos_path.$this->basename_video.'/frames/';
		$this->json_key_frames = $this->video_frames_path.$this->basename_video.".json";

		try{
			if ( !is_dir( $this->videos_path )){
				mkdir( $this->videos_path,0775,true);
				if ( ! is_dir( $this->videos_path))
					throw new Exception("could not create $this->videos_path\n");
			}
			
			if ( ! is_dir( $this->video_thumbnails_path )){
				mkdir ($this->video_thumbnails_path,0775,true);
				if ( ! is_dir( $this->video_thumbnails_path ))
					throw new Exception("could not create $this->video_thumbnails_path");
			}
			
			if ( ! is_dir( $this->video_mute_path )){
				mkdir ($this->video_mute_path,0775,true);
				if ( ! is_dir( $this->video_mute_path ) )
					throw new Exception("could not create $this->video_mute_path");
			}
			if ( ! is_dir( $this->video_gifs_path )){
				mkdir ($this->video_gifs_path,0775,true);
				if ( ! is_dir( $this->video_gifs_path ) )
					throw new Exception("could not create $this->video_gifs_path");
			}
			if ( ! is_dir( $this->video_frames_path )){
				mkdir ($this->video_frames_path,0775,true);
				if ( ! is_dir( $this->video_frames_path ) )
					throw new Exception("could not create $this->video_frames_path");
			}
		}
		catch(Exception $e){
			echo $e."\n";
			exit();
		}
	}

}

#$source = "hashtag.avi";
#$myvid = new GifTool($source);
//$myvid->to_thumbnails();
//$myvid->to_mute();
//$myvid->to_gif(135.000,140.000);
#$myvid->to_gif(135.000,140.000,'high');
//$myvid->to_gif(135.000,140.000,'medium',"-vf scale=320:-1");
