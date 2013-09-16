<?php

/** 
 *  CRONJOBS
 *  =========
 *  there is single call from cron to this file each minute, which farms out tasks to each module
 *  but indiv modules will be able to run at more or less frequent intervals as configured.
 * 
 *  @license:  GPLv3. 
 *  @author:   Peter 2013
 *  @revision  $Rev$
 *
 *  Cron setup, usually something along these lines
 *  * 6-17 * * * /usr/bin/php-cgi -f /home/www-data/html/blackbox/cronjobs.php >/dev/null 2>&1
 *  * * * * * /usr/bin/wget --quiet http://192.168.0.3/blackbox/cronjobs.php >/dev/null 2>&1
 *
 **/ 



### Prelim

//php set
ini_set('display_errors', 'off');

require("init.php");


### Read

//invoke
$blackbox= new Blackbox();

//read the module devices
$blackbox->process_modules();


### Generate graphs

$query= "
	select * from blackboxelements
	where id_view=':id_view' 
	and type='g'
	order by panetag,position
";	
$params= array('id_view'=>1);
$result= $db->query($query,$params) or codeerror('DB error',__FILE__,__LINE__);
while ($row= $db->fetch_row($result)) {
	$id_element=   $row['id_element'];
	$settings=      unserialize($row['settings']);
	make_graph($id_element,$settings,$blackbox->modules);
}

//print $profiler->dump();





//MAKE_GRAPH
function make_graph($id_element,$settings,$modules) {

	$day= date("Y-m-d");
	$records= $modules['midnite_classic']->get_datetimes('periodic'); //until minute series is fixed
	
	//hash the series keys
	//to match our axis to the available data 
	//to handle multiple points per minute, for now first one rules 
	$hash= array();
	foreach ($records as $n=>$datetime) {
		$rtime= date("H:i", strtotime($datetime));
		if (isset($hash[$rtime])) continue;
		$hash[$rtime]= $n;
	}
	
	//x and y data
	$ydata= $xdata= array();
	$stamp= "$day 06:00:00";
	while ($stamp <= "$day 19:00:00") {
		$hr=    date("H", strtotime($stamp)); 
		$mn=    date("i", strtotime($stamp)); 
		$rtime= date("H:i", strtotime($stamp)); 
		
		//set x label
		$xdata[]= (int)$mn ? '': (string)$hr;

		//set y values
		foreach ($settings['datapts'] as $series=>$bla) {
			$mod=	  $settings['datapts'][$series]['module'];
			$dp=    $settings['datapts'][$series]['datapoint'];
			$mult=  $settings['datapts'][$series]['multiplier'];
			if (!isset($ydata[$series])) $ydata[$series]= array();
			$ydata[$series][]= isset($hash[$rtime]) ? ($mult * $modules[$mod]->datapoints[$dp]->data[$hash[$rtime]]) : NULL;
		}
		//inc
		$stamp= date("Y-m-d H:i:s", strtotime("$stamp +1 min"));
	}		

	//data
	$ymax=$ymin=1e20; $data=array();
	$data[0]= $xdata; //x is data[0]
	foreach ($settings['datapts'] as $series=>$bla) {
		$ymax=  max($ymax,max($ydata[$series]));
		$ymin=  min($ymin,min($ydata[$series]));
		$data[]= array(
			'name'=>        $settings['datapts'][$series]['name'],
			'type'=>      	 'line',
			'color'=>       $settings['datapts'][$series]['linecolor'],
			'linewidth'=>   $settings['linethick'],
			'alpha'=>       80,
			'smooth'=>      (bool)$settings['linesmooth'],
			'joinmethod'=>  'angle',
			'data'=>        $ydata[$series],
		);
	}

	//set x and ymax
	if (!isset($settings['ymax']) or !$settings['ymax']) $settings['ymax']= 'auto';
	if (!isset($settings['ymin']) or !$settings['ymin']) $settings['ymin']= '0';
	$ymax= $settings['ymax']=='auto' ? $ymax : $settings['ymax'];
	$ymin= $settings['ymin']=='auto' ? $ymin : $settings['ymin'];

	//main graph setup
	$params= array(
		//chart
		'title'=>            '',
		'size'=>             array($settings['width'],$settings['height']), //w h  420/680 leaves 60px per hour!
		'scale'=>            1,                  //upscales everything for print
		'margins'=>          array(20,20,27,40), //t r b l
		'showborder'=>       false,
		'showlegend'=>       true,
		'legend_pos'=>       array(-120,34), //from tl, or use - for br aligned
		'shownote'=>         false,
		'note_pos'=>         array(50,34),   //from tl, or use - for br aligned
		'note_content'=>     '',
		'fontfolder'=>       '/home/www-data/html/lib/fonts/', //trailing slash
		'fontfile'=>         'SegoeSb.ttf',  //calibri.ttf
		'fontfilebold'=>     'calibrib.ttf', //nb:calibri wont smooth or rotate below 13pt
		'fontsize'=>          8,
		'fontcolor'=>        '#444',
		'border_color'=>     'rgb(150,150,150)',
		'grid_color_major'=> 'rgb(150,150,150)',
		'grid_color_minor'=> 'rgb(220,220,220)',

		//x axis
		'xaxistitle'=>       '',
		'xmode'=>           'adj',  //betw, adj
		'xusemajorgrid'=>   false,    //if false will show ticks only
		'xuseminorgrid'=>   false,    
		'xintervalmajor'=>  60,   //major grid every N points, default 1
		'xqtyminorgrids'=>  2,    //minor grid every N major grids, default 4, must be divisible into major, use 0 for no minor ticks

		//y axis
		'yaxistitle'=>       '',
		'ymode'=>           'fit',  //auto, fit or exact
		'yextents'=>        array($ymin,$ymax), //required for exact
		'yusemajorgrid'=>   true,    
		'yuseminorgrid'=>   false,    
		'yqtymajorgrids'=>      9,    //no of major grids, if fit, this will be rounded using multiples of 1,2, or 5
		'yqtyminorgrids'=>      4,    //minor grid every N major grids, default 4, must be divisible into major, use 0 for no minor ticks
		'yaxislabelspec'=>  ($ymax-$ymin) > 10 ? 'decimal(0)' : 'decimal(1)',

		//series
		'downsample'=>         $settings['average'],           //down samples overly detailed datasets, average every N points 
		'usedatapoints'=>      false,
		'datapointsize'=>      1.75, //times the line thickness
		'datapointshape'=>     'square',
		'usedatalabels'=>      false, 
		'datalabelinterval'=>  60, //interval of xdata points
		'datalabelspec'=>      'decimal(1)', 
		'linejoinmethod'=>     'round',
	);

	//build graph
	$graph= new Graph($params,$data);
	$gfile= "tmp/current-graph-$id_element.png";
	$graph->savetofile($gfile);

	return true;
}




?>