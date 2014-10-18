<?php




class GifTool{
	// Berlioz toolbox


	function help (){
		echo "Usage: berlioz video_file operation";
		return 0;
	}



	function parse_video_info (){
		$cmd = "ffprobe".$this->verbose." -print_format json -show_format ".$this->videos_source.$this->source;
		$string = exec($cmd, $output, $exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");
		$json_raw = implode("\n", $output);
		$this->infos = json_decode($json_raw, true);
	}

	function to_thumbnails (){
		if(! is_dir($this->video_thumbnails_path)){
			echo $this->video_thumbnails_path;
			mkdir($this->video_thumbnails_path,0775,true);
			if(! is_dir($this->video_thumbnails_path))
				throw new Exception("could not create thumbnail path $this->video_thumbnails_path for video $this->infos['format']['filename']");
		}
		$cmd = "ffmpeg ".$this->verbose." -i ".$this->infos['format']['filename']." -f image2 -vf \"select='eq(pict_type,PICT_TYPE_I)'\" -vsync vfr ".$this->video_thumbnails_path."/thumb%04d.png";
		exec ($cmd,$output,$exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");

	}

	function to_gif($start=25.000,$stop=30.000,$quality='low',$scale=""){
		if($stop < $start)
			throw new Exception("Stop point is prior to start point");
		

		$duration = $stop-$start;
		$id = uniqid();
		if($quality === 'low'){
			$cmd="ffmpeg ".$this->verbose." -ss ".$start." -i ".$this->videos_source.$this->source." ".$scale." -t ".$duration." -r 10 ".$this->video_gifs_path.$id.".gif";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
		}
		elseif($quality === 'medium'){
			if(!is_dir($this->videos_frames.$id)){
				mkdir($this->videos_frames.$id,0775,true);
				if(!is_dir($this->videos_frames.$id))
					throw new Exception("Error Processing Request", 1);
			}
			$cmd = "ffmpeg ".$this->verbose." -ss ".$start." -i ".$this->videos_source.$this->source." ".$scale." -t ".$duration." -r 25 ".$this->videos_frames.$id."/ffout%03d.png";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
			$cmd = "convert -delay 5 -loop 0 ".$this->videos_frames.$id."/ffout*.png ".$this->video_gifs_path.$id.".gif";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
		}

		/*if($quality === 'high'){
			$cmd="ffmpeg ".$this->verbose." -ss ".$start." -i ".$this->videos_source.$this->source." -t ".$duration."  -acodec libvorbis -an ".$this->video_mute_path."output.ogg";
			exec($cmd,$output,$exit);
			if($exit != 0)
				throw new Exception("Error Processing Request $cmd", 1);
		}*/
		return $id;
	}

	function to_mute() {
		$cmd = "ffmpeg".$this->verbose."  -i ".$this->videos_source.$this->source."  -vf scale=320:-1 -c:v libx264 -crf 20 -an  ".$this->video_mute_path."'mute-'".$this->source;
		exec($cmd,$output,$exit);
		if($exit != 0)
			throw new Exception("Invalid exit code for: $cmd");
		
	}

	public function __construct($source,
								$ffprobe='/usr/local/bin/ffprobe',
								$videos_path='videos/',
								$videos_source='videos/sources/',
								$videos_frames='videos/frames/',
								$ffmpeg_path='/usr/local/bin/ffmpeg',
								$verbose=0){
		$this->source = $source;
		$this->ffprobe = $ffprobe;
		$this->videos_path= $videos_path;
		$this->videos_frames= $videos_frames;
		$this->videos_source= $videos_source;
		if($verbose)
			$this->verbose = " -v quiet ";
		else
			$this->verbose = "";
	//path to ffmpeg executable
		$this->ffmpeg_path=$ffmpeg_path;

		try{
			if ( ! file_exists($this->videos_source.$this->basename_video) ) {
				throw new Exception("source file does not exist:".$this->source);
			}
		}
		catch(Exception $e){
			echo $e."\n";
			exit();
		}

		$this->parse_video_info($ffprobe,$source);

		$this->basename_video=basename($source);
		$this->video_thumbnails_path=$this->videos_path.$this->basename_video.'/thumbnails/';
		$this->video_mute_path=$this->videos_path.$this->basename_video.'/mute/';
		$this->video_gifs_path=$this->videos_path.$this->basename_video.'/gifs/';
		

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
			if ( ! is_dir( $this->videos_frames )){
				mkdir ($this->videos_frames,0775,true);
				if ( ! is_dir( $this->videos_frames ) )
					throw new Exception("could not create $this->videos_frames");
			}
		}
		catch(Exception $e){
			echo $e."\n";
			exit();
		}
	}

}

$source = "hashtag.avi";
$myvid = new GifTool($source);
//$myvid->to_thumbnails();
//$myvid->to_mute();
//$myvid->to_gif(135.000,140.000);
$myvid->to_gif(135.000,140.000,'high');
//$myvid->to_gif(135.000,140.000,'medium',"-vf scale=320:-1");



?>