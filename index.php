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

$total_count = 0;

$googlebot_crawl = array('codes' => array(), 'exts' => array(), 'dates' => array(), 'regexes' => array());

// change these to anything you want to particularly note in your crawls
// the first part gives a key used for reporting the area, the second is the regex to match with agains the URL path
$regexes = array(
	'Fly Me to the Moon' => '/^\/fly-me-to-the-moon.+/',
	'Parameter URIs' => '/^.+\?.+/'
);

foreach ($log_lines as $line) {
	$entry = $parser->parse($line);

	if (is_object($entry) && strpos($entry->requestFrom, 'Googlebot')) {
		$total_count++;

		$cleaned_path = (strpos($entry->urlPath, "?") !== FALSE) ? substr($entry->urlPath, 0, strpos($entry->urlPath, "?")) : $entry->urlPath;

		$parts = pathinfo($cleaned_path);
		$ext = (isset($parts['extension'])) ? $parts['extension'] : 'html';

		if (isset($googlebot_crawl['codes'][$entry->responseCode]['requests']))
			$googlebot_crawl['codes'][$entry->responseCode]['requests']++;
		else
			$googlebot_crawl['codes'][$entry->responseCode]['requests'] = 1;

		if (isset($googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]))
			$googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]++;
		else
			$googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath] = 1;

		if (isset($googlebot_crawl['exts'][$ext]['requests']))
			$googlebot_crawl['exts'][$ext]['requests']++;
		else
			$googlebot_crawl['exts'][$ext]['requests'] = 1;

		if (isset($googlebot_crawl['exts'][$ext]['urlPaths'][$entry->urlPath]))
			$googlebot_crawl['exts'][$ext]['urlPaths'][$entry->urlPath]++;
		else
			$googlebot_crawl['exts'][$ext]['urlPaths'][$entry->urlPath] = 1;

		if (isset($googlebot_crawl['dates'][$entry->date]))
			$googlebot_crawl['dates'][$entry->date]++;
		else
			$googlebot_crawl['dates'][$entry->date] = 1;

		foreach ($regexes as $name => $regex) {
			if (preg_match($regex, $entry->urlPath, $matches)) {
				if (isset($googlebot_crawl['regexes'][$name]['requests']))
					$googlebot_crawl['regexes'][$name]['requests']++;
				else
					$googlebot_crawl['regexes'][$name]['requests'] = 1;

				if (isset($googlebot_crawl['regexes'][$name]['urlPaths'][$entry->urlPath]))
					$googlebot_crawl['regexes'][$name]['urlPaths'][$entry->urlPath]++;
				else
					$googlebot_crawl['regexes'][$name]['urlPaths'][$entry->urlPath] = 1;
			}
		}
	}
}

foreach ($googlebot_crawl['codes'] as $code => $code_data) {
	arsort($googlebot_crawl['codes'][$code]['urlPaths']);
}

foreach ($googlebot_crawl['regexes'] as $name => $name_data) {
	arsort($googlebot_crawl['regexes'][$name]['urlPaths']);
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Log File Analyser</title>
	<link rel="stylesheet" type="text/css" href="minimal_css.min.css">
</head>
<body>
	<div id="wrapper" class="row">
		<div class="col col-xs-12">
			<h1>Log File Analyser</h1>
			<script src="https://code.highcharts.com/highcharts.js"></script>
			<h2>Resources Crawled per Day</h2>
			<div class="content">
				<div id="chart"></div>
			</div>
			<script>
			window["chart"] = new Highcharts.Chart({"credits":{"enabled":false},"chart":{"backgroundColor":"#fefefe","renderTo":"chart","type":"spline","spacingTop":20,"spacingBottom":20},"title":{"text":null},"colors":["#3fa9f5","#476974","#3ca1c1","#4ccbf4","#96dff6","#c9e8f6"],"legend":{"enabled":true,"margin":30},"plotOptions":{"series":{"animation":false}},"tooltip":{"backgroundColor":"#3fa9f5","borderColor":"#3fa9f5","borderRadius":0,"shadow":false,"style":{"color":"#ffffff","padding":"15px","font-size":"12px"}},"xAxis":{"tickWidth":0,"labels":{"y":20},"categories":["<?php echo implode('","', array_keys($googlebot_crawl['dates'])) ?>"]},"yAxis":{"tickWidth":0,"labels":{"y":0},"title":{"text":"Values"}},"series":[{"name":"Crawled resources per day","data":[<?php echo implode(',', $googlebot_crawl['dates']) ?>]}]});
			</script>
			<p><small>Created with the <a href="https://builtvisible.com/highcharts-generator/">Highcharts Generator tool</a></small></p>
		</div>
		<div class="col col-xs-12">
			<h2>Resources Crawled by Status Code</h2>
			<?php
			foreach (array(301,404) as $status_code):
				if (isset($googlebot_crawl['codes'][$status_code])): ?>
			<h3><?php echo $status_code ?> URIs (<small><?php echo $googlebot_crawl['codes'][$status_code]['requests'] ?></small>)</h3>
			<div class="content">
				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th>Count</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($googlebot_crawl['codes'][$status_code]['urlPaths'] as $path => $count): ?>
						<tr>
							<td><?php echo $path ?></td>
							<td><?php echo $count ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
				endif;
			endforeach;
			?>
		</div>
		<div class="col col-xs-12">
			<h2>Resources Crawled by Extension</h2>
			<?php
			foreach (array('css','js','png','jpg') as $ext):
				if (isset($googlebot_crawl['exts'][$ext])): ?>
			<h3><?php echo $ext ?> URIs (<small><?php echo $googlebot_crawl['exts'][$ext]['requests'] ?></small>)</h3>
			<div class="content">
				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th>Count</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($googlebot_crawl['exts'][$ext]['urlPaths'] as $path => $count): ?>
						<tr>
							<td><?php echo $path ?></td>
							<td><?php echo $count ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
				endif;
			endforeach;
			?>
		</div>
		<div class="col col-xs-12">
			<h2>Resources Crawled by Regex</h2>
			<?php
			if (count($googlebot_crawl['regexes']) > 0):
				foreach ($googlebot_crawl['regexes'] as $title => $dataset):
			?>
			<h3><?php echo $title ?> (<small><?php echo $googlebot_crawl['regexes'][$title]['requests'] ?></small>)</h3>
			<div class="content">
				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th>Count</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($googlebot_crawl['regexes'][$title]['urlPaths'] as $path => $count): ?>
						<tr>
							<td><?php echo $path ?></td>
							<td><?php echo $count ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
				endforeach;
			endif;
			?>
		</div>
	</div>
</body>
</html>