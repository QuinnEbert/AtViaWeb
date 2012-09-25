<?php
// Function returns true or false indicating whether there's any hope of $filename beginning with a shebang line, this means:
// true response means that:
//   + file exists
//   + file is readable
//   + file is at least 3 bytes long
//   + first three bytes are exactly "#!/"
// false response means that:
//   + one or more of the above are unsatisfied
// Be aware that this function *is* optimized to use as little system resources as possible.  I.E. it won't read more than
// three bytes from the file, and if the file is less than three bytes in size, it won't even be opened at all.
function banghope($filename) {
	if (!file_exists($filename))
		return false;
	if (!is_readable($filename))
		return false;
	if (filesize($filename) < 3)
		return false;
	$handle = fopen($filename, "rb");
	if ($handle === false)
		return false;
	$a = fgetc($handle); $b = fgetc($handle); $c = fgetc($handle);
	fclose($handle);
	$testing = "{$a}{$b}{$c}";
	if ($testing != "#!/")
		return false;
	return true;
}

// Function returns a zero-based one-dimensional array indicating which files (searching recursively) under $directoryname
// have shebang lines at the tops of them.
// Set $eatPath to true to output only the basename() of each found file rather than the full path (use with caution!)
function findshellscripts($directoryname,$eatPath = false) {
	$currDir = escapeshellarg($directoryname);
	$theList = explode("\n",trim(`find $currDir`));
	$currNum = 0;
	foreach($theList as $tryItem) {
		if (! is_dir($tryItem)) {
			$inFile = $tryItem;
			if (banghope($inFile)) {
				if (! $eatPath) {
					$myFiles[$currNum] = $tryItem;
				} else {
					$myFiles[$currNum] = basename($tryItem);
				}
				$currNum++;
			}
		}
	}
	return $myFiles;
}
?>
<html>
<head>
<title>At via Web (Web Command Scheduler)</title>
<script language="JavaScript">
<!--
 function submitIt() {
	//document.getElementById('jobexec').disabled = '';
	document.getElementById('jobform').submit();
 }
 function enterArg(args) {
	document.getElementById('jobexec').value = document.getElementById('jobpick').value+' '+args.value;
 }
 function picksJob(jobs) {
	/*if (jobs.selectedIndex > 0) {*/
		//document.getElementById('jobexec').disabled = 'disabled';
		//document.getElementById('jobargs').disabled = '';
		document.getElementById('jobargs').value = '';
		document.getElementById('jobexec').value = jobs.value;
	/*} else {
		document.getElementById('jobargs').disabled = 'disabled';
		document.getElementById('jobexec').disabled = '';
		document.getElementById('jobexec').value = '';
	}*/
 }
 -->
</script>
</head>
<body>
<h1 align="center">At via Web</h1>
<h2 align="center">Web Command Scheduler</h2>
<?php
if (isset($_REQUEST['action']) && strlen($_REQUEST['action'])) {
  if ($_REQUEST['action']=='Add Job') {
    /*** Add an At job ***/
    // Sanitize single quotes in the scheduled command (WARNING: a best effort):
    $in_exec = str_replace("'","'\"'\"'",$_REQUEST['jobexec']);
    // Run the command, store the output (we might output it in a future build):
    $in_time = $_REQUEST['jobtime'];
    $add_out = `echo '$in_exec' | at $in_time`;
  } elseif ($_REQUEST['action']=='Delete Job') {
    /*** Remove an At job ***/
    $in_rmid = $_REQUEST['jobID'];
    $add_out = `atrm $in_rmid`;
  } else {
    // A header to denote output of our issue(s):
    echo('<h3 align="center">Your Last Request</h3>'."\n");
    echo("<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\"><tr><td valign=\"middle\" align=\"center\">Action &quot;{$_REQUEST['action']}&quot; not supported!</i></td></tr></table>\n");
  }
}
?>
<h3 align="center">Your Scheduled Jobs</h3>
<?php
// Fetch the list of `at` jobs:
$results = trim(`atq`);
if (strlen($results)) {
  // Excellent, we have jobs, prepare to parse them:
  $results = explode("\n",$results);
  // Parse through the fetched list, make it useful:
  foreach ($results as $index => $value) {
    $theJobs[$index] = explode("\t",$value);
    // Strip useless info (' a <username>') from the end of the date/time bit:
    $tmpJDIB = explode(' a',$theJobs[$index][1],2);
    $theJobs[$index][1] = $tmpJDIB[0];
    /*
     * This results in an array with the following "parsed" format:
     * 
     * [0]    = Job ID
     * [1]    = Scheduled Date + Time
     * [2]    = Commands in job (constructed just below...)
     */
    // Try to also construct the command line via running `at -c <...>`:
    $jobsNum = $theJobs[$index][0];
    $jobCmds = explode("\n",trim(`at -c $jobsNum`));
    $jcIndex = (count($jobCmds)-1);
    // Strip the pathname from commands (very tacky approach):
    $tmpJDIA = explode('/',trim($jobCmds[$jcIndex]));
    $jcIndex = (count($tmpJDIA)-1);
    $theJobs[$index][2] = $tmpJDIA[$jcIndex];
  }
  // Output a table header:
  echo('<table align="center" border="1" cellspacing="1" cellpadding="4"><tr><td><b>Job ID</b></td><td><b>Scheduled Date and Time</b></td><td><b>Command Set</b></td><td><b>Job Actions</b></td></tr>');
  // Output the list (if we have any jobs):
  foreach ($theJobs as $atJob) {
    // Job information:
    echo('<tr><td>'.$atJob[0].'</td><td>'.$atJob[1].'</td><td><pre>'.$atJob[2].'</pre></td>');
    // Job actions:
    echo('<td><form style="margin-bottom: 0px; margin-top: 0px;" action="'.basename(__FILE__).'" method="GET"><input type="hidden" name="jobID" value="'.$atJob[0].'" /><input style="margin-bottom: 0px; margin-top: 0px;" type="submit" name="action" value="Delete Job" /></form></td>');
    // End of table row:
    echo('</tr>');
  }
  // Output table closing tag:
  echo('</table>');
} else {
  /* No jobs, let the user know: */
  // Output a table header:
  echo('<table align="center" border="1" cellspacing="1" cellpadding="4" width="30%">');
  // Output a "no jobs scheduled" message:
  echo('<tr><td align="center"><b><i>No jobs currently scheduled!</i></b></td></tr>');
  // Output table closing tag:
  echo('</table>');
}
?>
<h3 align="center">Schedule New Job</h3>
<table align="center" border="1" cellspacing="1" cellpadding="4" width="25%">
<tr>
<td align="center" valign="middle">
  <form style="margin-bottom: 0px;" action="<?php echo(basename(__FILE__)); ?>" id="jobform" method="GET">
    <label for="jobtime">At Job Time Specification:</label><br />
    <input type="text" name="jobtime" id="jobtime" size="32" /><br />
    <input type="hidden" name="jobexec" id="jobexec" size="32" />
    <label for="jobpick">Run Command</label><br />
    <select name="jobpick" id="jobpick" onChange="picksJob(this)">
	<?php
	foreach (findshellscripts(dirname(__FILE__)) as $aScript) {
		if ($aScript!='/') {
			?><option value="<?php echo($aScript); ?>"><?php echo(basename($aScript)); ?></option><?php
		}
	}
	?>
	</select><br />
    <label for="jobargs">Enter Arguments</label><br />
    <input type="text" name="jobargs" id="jobargs" size="32" disabled="disabled" onChange="enterArg(this)" /><br />
    <input type="hidden" name="action" value="Add Job" />
    <input style="margin-bottom: 0px;" type="button" name="Add Job" value="Add Job" onClick="submitIt()" />
  </form>
</td>
</tr>
</table>
<h4 align="center" style="margin-bottom: 0px;">At via Web is open-source software</h4>
<h5 align="center" style="margin-top: 0px;">by <a href="http://www.quinnebert.net/">Quinn &quot;Zed&quot; Ebert</a></h5>
</body>
</html>