<?php
/*
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/ // uncomment this if you want to debug

ini_set('memory_limit', '1024M'); // set based on how much you've got available
date_default_timezone_set('UTC'); // set to the timezone your server uses

function debug ($data, $mode = 0, $title = NULL)
{
	$backtrace = debug_backtrace();

	echo ($title != NULL) ? "<h2>$title</h2>" : '<h2>debug</h2>';

	if (isset($backtrace[1]))
		echo '<p>'.$backtrace[0]['file'].': '.$backtrace[0]['line'].'</p><pre>';
	else
		echo '<p>'.$backtrace[0]['file'].': '.$backtrace[0]['line'].'</p><pre>';

	var_dump($data);
	echo '</pre>';

	exit;
}

class LogParser
{
	private $regex;

	public function setRegex ($regex) { $this->regex = $regex; }
	public function getRegex () { return (string) $this->regex; }

	public function parse ($line) {
		if (!preg_match($this->regex, $line, $matches))
			return FALSE;

		$entry = new stdClass();

		foreach (array_filter(array_keys($matches), 'is_string') as $key) {
			if ('time' === $key && true !== $stamp = strtotime($matches[$key]))
				$entry->stamp = $stamp;

			$entry->{$key} = $matches[$key];
		}

		return $entry;
	}
}

$parser = new LogParser();

$parser->setRegex("/^(?P<ipAddress>\S+) (\S+) (\S+) \[(?P<date>[^:]+):(?P<time>\d+:\d+:\d+) (?P<timezoneOffset>[^\]]+)\] \"(?P<httpMethod>\S+) (?P<urlPath>.*?) (?P<responseType>\S+)\" (?P<responseCode>\S+) (?P<bytesSent>\S+) (?P<referrer>\".*?\") (?P<requestFrom>\".*?\")$/");

$log_lines = file('2016-08-15-access.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // testing mode
// $log_lines = file(date('Y-m-d').'-access.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // prod mode

$dates = $crawl = array();

// change these to anything you want to particularly note in your crawls
// the first part gives a key used for reporting the area, the second is the regex to match with agains the URL path
$regexes = array(
	'Parameters' => '/^.+\?.+/',
	'UTM' => '/^.+[&|\?]utm_.+/'
);

foreach ($log_lines as $line) {
	$entry = $parser->parse($line);

	if (is_object($entry) && strpos($entry->requestFrom, 'Googlebot')) {
		if (!isset($dates[$entry->date]))
			$dates[$entry->date] = 1;
		else
			$dates[$entry->date]++;

		$cleaned_path = (strpos($entry->urlPath, "?") !== FALSE) ? substr($entry->urlPath, 0, strpos($entry->urlPath, "?")) : $entry->urlPath;

		$parts = pathinfo($cleaned_path);
		$ext = (isset($parts['extension'])) ? $parts['extension'] : 'html';

		if (!isset($crawl[$entry->urlPath])) {
			$crawl[$entry->urlPath] = array(
				'requests' => 1,
				'ext' => $ext,
				'codes' => array($entry->responseCode => 1),
				'dates' => array($entry->date),
				'regexes' => array()
			);

			foreach ($regexes as $title => $regex) {
				if (preg_match($regex, $entry->urlPath, $matches)) {
					if (!isset($crawl[$entry->urlPath]['regexes'][$title]))
						$crawl[$entry->urlPath]['regexes'][$title] = 1;
					else
						$crawl[$entry->urlPath]['regexes'][$title]++;
				}
			}
		}
		else {
			$crawl[$entry->urlPath]['requests']++;

			if (!isset($crawl[$entry->urlPath]['codes'][$entry->responseCode]))
				$crawl[$entry->urlPath]['codes'][$entry->responseCode] = 1;
			else
				$crawl[$entry->urlPath]['codes'][$entry->responseCode]++;

			if (!in_array($entry->date, $crawl[$entry->urlPath]['dates']))
				$crawl[$entry->urlPath]['dates'][] = $entry->date;
		}
	}
}

foreach ($crawl as $path => $row) {
	arsort($crawl[$path]['codes']);
}

// sort everything by requests
uasort($crawl, function ($a, $b) {
	if ($a['requests'] > $b['requests'])
		return -1;
	elseif ($a['requests'] < $b['requests'])
		return 1;
	else
		return 0;
});
?>
<!DOCTYPE html>
<html>
<head>
	<title>Log File Analyser</title>
	<link rel="stylesheet" type="text/css" href="minimal_css.min.css">
</head>
<body>
	<nav id="navbar">
		<ul>
			<?php
			foreach (array('html','css','js','png','jpg') as $ext):
				foreach (array(200,301,404) as $status_code):
					foreach ($crawl as $path => $row):
						if ($row['ext'] === $ext && isset($row['codes'][$status_code])):
			?>
			<li><a href="#<?php echo $ext.$status_code ?>"><?php echo "$ext $status_code" ?></a></li>
			<?php
							continue 2;
						endif;
					endforeach;
				endforeach;
			endforeach;
			foreach ($regexes as $title => $regex):
				foreach ($crawl as $path => $row):
					if (isset($row['regexes'][$title])):
		?>
		<li><a href="#<?php echo $title ?>"><?php echo "$title" ?></a></li>
		<?php
						continue 2;
					endif;
				endforeach;
			endforeach;
			?>
		</ul>
	</nav>
	<div id="wrapper" class="row">
		<div class="col col-xs-12">
			<h1>Log File Analyser</h1>
			<script src="https://code.highcharts.com/highcharts.js"></script>
			<h2>Resources Crawled per Day</h2>
			<div class="content">
				<div id="chart"></div>
			</div>
			<script>
			window["chart"] = new Highcharts.Chart({"credits":{"enabled":false},"chart":{"backgroundColor":"#fefefe","renderTo":"chart","type":"spline","spacingTop":20,"spacingBottom":20},"title":{"text":null},"colors":["#3fa9f5","#476974","#3ca1c1","#4ccbf4","#96dff6","#c9e8f6"],"legend":{"enabled":true,"margin":30},"plotOptions":{"series":{"animation":false}},"tooltip":{"backgroundColor":"#3fa9f5","borderColor":"#3fa9f5","borderRadius":0,"shadow":false,"style":{"color":"#ffffff","padding":"15px","font-size":"12px"}},"xAxis":{"tickWidth":0,"labels":{"y":20},"categories":["<?php echo implode('","', array_keys($dates)) ?>"]},"yAxis":{"tickWidth":0,"labels":{"y":0},"title":{"text":"Values"}},"series":[{"name":"Crawled resources per day","data":[<?php echo implode(',', $dates) ?>]}]});
			</script>
			<p><small>Created with the <a href="https://builtvisible.com/highcharts-generator/">Highcharts Generator tool</a></small></p>
		</div>
		<div class="col col-xs-12">
			<h2>Resources Crawled by Status Code & Ext</h2>
			<?php
			foreach (array('html','css','js','png','jpg') as $ext):
				foreach (array(200,301,404) as $status_code):
				?>
			<h3 id="<?php echo "$ext$status_code" ?>"><?php echo "$ext $status_code" ?> Status Code URIs</h3>
			<div class="content">
				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th>Count</th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ($crawl as $path => $row):
						if ($row['ext'] === $ext && isset($row['codes'][$status_code])): ?>
						<tr>
							<td><?php echo $path ?></td>
							<td><?php echo $row['codes'][$status_code] ?></td>
						</tr>
						<?php
						endif;
					endforeach;
					?>
					</tbody>
				</table>
			</div>
			<?php
				endforeach;
			endforeach;
			?>
		</div>
		<div class="col col-xs-12">
			<h2>Resources Crawled by Regex</h2>
			<?php foreach ($regexes as $title => $regex): ?>
			<h3 id="<?php echo $title ?>"><?php echo $title ?></h3>
			<div class="content">
				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th>Count</th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ($crawl as $path => $row):
						if (isset($row['regexes'][$title])): ?>
						<tr>
							<td><?php echo $path ?></td>
							<td><?php echo $row['regexes'][$title] ?>(<small><?php echo $row['requests'] ?></small>)</td>
						</tr>
					<?php
						endif;
					endforeach;
					?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</body>
</html>