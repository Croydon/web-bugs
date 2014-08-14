<?php
// Start session
session_start();

// Obtain common includes
require_once '../include/prepend.php';

// Redirect early if a bug id is passed as search string
$search_for_id = (isset($_GET['search_for'])) ? (int) $_GET['search_for'] : 0;
if ($search_for_id) {
	redirect("bug.php?id={$search_for_id}");
}

// For bug count only, used in places like doc.php.net
$count_only = isset($_REQUEST['count_only']) && $_REQUEST['count_only'];

bugs_authenticate($user, $pw, $logged_in, $user_flags);

$is_security_developer = ($user_flags & (BUGS_TRUSTED_DEV | BUGS_SECURITY_DEV));

$newrequest = http_build_query(array_merge($_GET, $_POST));

if (!$count_only) {
	response_header(
		'Bugs :: Search', "
			<link rel='alternate' type='application/rss+xml' title='Search bugs - RDF' href='rss/search.php?{$newrequest}'>
			<link rel='alternate' type='application/rss+xml' title='Search bugs - RSS 2.0' href='rss/search.php?format=rss2&{$newrequest}'>
	");
}

// Include common query handler (used also by rss/search.php)
require "{$ROOT_DIR}/include/query.php";

if (isset($_GET['cmd']) && $_GET['cmd'] == 'display')
{
	if (!isset($res)) {
		$errors[] = 'Invalid query';
	} else {
		// For count only, simply print the count and exit
		if ($count_only) {
			echo (int) $total_rows;
			exit;
		}

		// Selected packages to search in
		$package_name_string = '';
		if (count($package_name) > 0) {
			foreach ($package_name as $type_str) {
				$package_name_string.= '&amp;package_name[]=' . urlencode($type_str);
			}
		}

		// Selected packages NOT to search in
		$package_nname_string = '';
		if (count($package_nname) > 0) {
			foreach ($package_nname as $type_str) {
				$package_nname_string.= '&amp;package_nname[]=' . urlencode($type_str);
			}
		}

		$link_params = [
			'search_for'  => urlencode($search_for),
			'project'     => urlencode($project),
			'php_os'      => urlencode($php_os),
			'php_os_not'  => $php_os_not,
			'author_email' => urlencode($author_email),
			'bug_type'    => urlencode($bug_type),
			'boolean'     => $boolean_search,
			'bug_age'     => $bug_age,
			'bug_updated' => $bug_updated,
			'order_by'    => $order_by,
			'direction'   => $direction,
			'limit'       => $limit,
			'phpver'      => urlencode($phpver),
			'cve_id'      => urlencode($cve_id),
			'cve_id_not'  => $cve_id_not,
			'patch'       => urlencode($patch),
			'pull'        => urlencode($pull),
			'assign'      => urlencode($assign)
		];

		if ($is_security_developer) {
			$link_params[] = ['private' => $private];
		}

		// Remove empty URL parameters
		foreach ($link_params as $index => $param) {
			if (empty($param))
				unset($link_params[$index]);
		}

		// Create link params string
		$link_params_string = '';
		foreach ($link_params as $index => $param) {
			$link_params_string .= "&amp;$index=$param";
		}

		$link = "search.php?cmd=display{$package_name_string}{$package_nname_string}{$link_params_string}";
		$clean_link = "search.php?cmd=display{$link_params_string}";

		if (isset($_GET['showmenu'])) {
			$link .= '&amp;showmenu=1';
		}

		if (!$rows) {
			$errors[] = 'No bugs were found.';
			display_bug_error($errors, 'warnings', '');
		} else {
			display_bug_error($warnings, 'warnings', 'WARNING:');
			$link .= '&amp;status=' . urlencode($status);
			$package_count = count($package_name);
?>

<table border="0" cellspacing="2" width="100%">

<?php show_prev_next($begin, $rows, $total_rows, $link, $limit);?>

<?php if ($package_count === 1) { ?>
 <tr>
  <td class="search-prev_next" style="text-align: center;" colspan="10">
<?php
	$pck = htmlspecialchars($package_name[0]);
	$pck_url = urlencode($pck);
	echo "Bugs for {$pck}\n";
?>
  </td>
 </tr>
<?php } ?>

 <tr>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=id">ID#</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=ts1">Date</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=ts2">Last Modified</a></th>
<?php if ($package_count !== 1) { ?>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=package_name">Package</a></th>
<?php } ?>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=bug_type">Type</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=status">Status</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=php_version">PHP Version</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=php_os">OS</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=sdesc">Summary</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=assign">Assigned</a></th>
 </tr>
<?php

			while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
				$status_class = $row['private'] == 'Y' ? 'Sec' : $tla[$row['status']];

				echo ' <tr valign="top" class="' , $status_class, '">' , "\n";

				// Bug ID
				echo '  <td align="center"><a href="bug.php?id=', $row['id'], '">', $row['id'], '</a>';
				echo '<br><a href="bug.php?id=', $row['id'], '&amp;edit=1">(edit)</a></td>', "\n";

				// Date
				echo '  <td align="center">', format_date(strtotime($row['ts1'])), "</td>\n";

				// Last Modified
				$ts2 = strtotime($row['ts2']);
				echo '  <td align="center">' , ($ts2 ? format_date($ts2) : 'Not modified') , "</td>\n";

				// Package
				if ($package_count !== 1) {
					$pck = htmlspecialchars($row['package_name']);
					$pck_url = urlencode($pck);
					echo "<td><a href='{$clean_link}&amp;package_name[]={$pck_url}'>{$pck}</a></td>\n";
				}

				/// Bug type
				$type_idx = !empty($row['bug_type']) ? $row['bug_type'] : 'Bug';
				echo '  <td>', htmlspecialchars($bug_types[$type_idx]), '</td>', "\n";

				// Status
				echo '  <td>', htmlspecialchars($row['status']);
				if ($row['status'] == 'Feedback' && $row['unchanged'] > 0) {
					printf ("<br>%d day%s", $row['unchanged'], $row['unchanged'] > 1 ? 's' : '');
				}
				echo '</td>', "\n";

				/// PHP version
				echo '  <td>', htmlspecialchars($row['php_version']), '</td>';

				// OS
				echo '  <td>', $row['php_os'] ? htmlspecialchars($row['php_os']) : '&nbsp;', '</td>', "\n";

				// Short description
				echo '  <td><a href="bug.php?id=', $row['id'], '">', $row['sdesc']  ? htmlspecialchars($row['sdesc']) : '&nbsp;', '</a></td>', "\n";

				// Assigned to
				echo '  <td>',  ($row['assign'] ? ("<a href=\"{$clean_link}&amp;assign=" . urlencode($row['assign']) . '">' . htmlspecialchars($row['assign']) . '</a>') : '&nbsp;'), '</td>';
				echo " </tr>\n";
			}

			show_prev_next($begin, $rows, $total_rows, $link, $limit);

			echo "</table>\n\n";
		}

		response_footer();
		exit;
	}
}

display_bug_error($errors);
display_bug_error($warnings, 'warnings', 'WARNING:');

?>
<form id="asearch" method="get" action="search.php">

<div class="container-fluid" id="advanced-search">
	<div class="row">
		<div class="col-xs-1">
			<b>Find bugs</b>
		</div>
		<div class="col-xs-3">
			<label for="keywords">with all or any of the w<span class="accesskey">o</span>rds</label>
		</div>
		<div class="col-xs-4">
			<input type="text" id="keywords" name="search_for" class="form-control input-sm" value="<?php echo htmlspecialchars($search_for, ENT_COMPAT, 'UTF-8'); ?>" size="20" maxlength="255" accesskey="o">
			<small>
				<?php show_boolean_options($boolean_search) ?>
				(<a href="search-howto.php" target="_new">?</a>)
			</small>
		</div>
		<div class="col-xs-4">
			<div class="form-inline">
				<label for="limit"><b>Display pro page</b></label> <select id="limit" name="limit" class="form-control input-sm"><?php show_limit_options($limit);?></select>
				&nbsp;
				<select name="order_by" class="form-control input-sm"><?php show_order_options($limit);?></select>
			</div>
			<small>
				<label class="radio-inline"><input type="radio" name="direction" value="ASC" <?php if($direction != "DESC") { echo('checked="checked"'); }?>> Ascending </label>

				<label class="radio-inline"><input type="radio" name="direction" value="DESC" <?php if($direction == "DESC") { echo('checked="checked"'); }?>> Descending </label>
			</small>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="status" accesskey="s">with <b><span class="accesskey">s</span>tatus</b></label>
		</div>
		<div class="col-xs-4">
			<select id="status" name="status" class="form-control input-sm"><?php show_state_options($status);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="bug_type"> with <b>type</b></label>
		</div>
		<div class="col-xs-4">
			<select id="bug_type" name="bug_type" class="form-control input-sm"><?php show_type_options($bug_type, true);?></select>
		</div>
		<div class="col-xs-4">
			<input type="hidden" name="cmd" value="display">
			<button type="submit" class="btn btn-primary btn-sm" accesskey="r">Sea<span class="accesskey">r</span>ch</button>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="project"> with <b>project</b></label>
		</div>
		<div class="col-xs-4">
			<select id="project" name="project" class="form-control input-sm"><?php show_project_options($project, true);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="category" accesskey="c">for these <b>pa<span class="accesskey">c</span>kages</b></label>
		</div>
		<div class="col-xs-4">
			<select id="category" name="package_name[]" class="form-control input-sm" multiple="multiple" size="6"><?php show_package_options($package_name, 2);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="exclude_categorys"><b>NOT</b> for these <b>packages</b></label>
		</div>
		<div class="col-xs-4">
			<select id="exclude_categorys" name="package_nname[]" class="form-control input-sm" multiple="multiple" size="6"><?php show_package_options($package_nname, 2);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="php_os">with <b>operating system</b></label>
		</div>
		<div class="col-xs-4 form-inline">
			<input type="text" id="php_os" name="php_os" class="form-control input-sm" value="<?php echo htmlspecialchars($php_os, ENT_COMPAT, 'UTF-8'); ?>">
			<div class="checkbox"><label><input type="checkbox" name="php_os_not" value="1" <?php echo ($php_os_not == 'not') ? 'checked="checked"' : ''; ?>> NOT</label></div>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="phpver">reported with <b>PHP version</b></label>
		</div>
		<div class="col-xs-4">
			<input type="text" id="phpver" name="phpver" class="form-control input-sm" value="<?php echo htmlspecialchars($phpver, ENT_COMPAT, 'UTF-8'); ?>">
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="cve_id">reported with <b>CVE-ID</b></label>
		</div>
		<div class="col-xs-4 form-inline">
			<input type="text" id="cve_id" name="cve_id" class="form-control input-sm" value="<?php echo htmlspecialchars($cve_id, ENT_COMPAT, 'UTF-8'); ?>">
			<div class="checkbox"><label><input type="checkbox" name="cve_id_not" value="1" <?php echo ($cve_id_not == 'not') ? 'checked="checked"' : ''; ?>> NOT</label></div>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="assign"><b>assigned</b> to</label>
		</div>
		<div class="col-xs-4">
			<input type="text" id="assign" name="assign" class="form-control input-sm" value="<?php echo htmlspecialchars($assign, ENT_COMPAT, 'UTF-8'); ?>">
			<?php
				if (!empty($auth_user->handle)) {
					$u = htmlspecialchars($auth_user->handle);
					echo "<input type=\"button\" value=\"set to $u\" onclick=\"form.assign.value='$u'\">";
				}
			?>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="author_email">with <b>author e<span class="accesskey">m</span>ail</b></label>
		</div>
		<div class="col-xs-4">
			<input accesskey="m" type="text" id="author_email" name="author_email" class="form-control input-sm" value="<?php echo htmlspecialchars($author_email, ENT_COMPAT, 'UTF-8'); ?>">
			<?php
				if (!empty($auth_user->handle)) {
					$u = htmlspecialchars($auth_user->handle);
					echo "<input type=\"button\" value=\"set to $u\" onclick=\"form.author_email.value='$u@php.net'\">";
				}
			?>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="bug_age"><b>submitted</b></label>
		</div>
		<div class="col-xs-4">
			<select id="bug_age" name="bug_age" class="form-control input-sm"><?php show_byage_options($bug_age);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="bug_updated"><b>updated</b></label>
		</div>
		<div class="col-xs-4">
			<select id="bug_updated" name="bug_updated" class="form-control input-sm"><?php show_byage_options($bug_updated);?></select>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="patch">only with a <b>patch attached</b>
		</div>
		<div class="col-xs-4">
			<input type="checkbox" id="patch" name="patch" value="Y" <?php echo $patch == 'Y' ? " checked" : "" ?>></label>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label>only with a <b>pull request</b>
		</div>
		<div class="col-xs-4">
			<input type="checkbox" id="pull" name="pull" value="Y" <?php echo $pull == 'Y' ? " checked" : "" ?>></label>
		</div> 
		<div class="col-xs-4">
		</div>
	</div>
	
	<?php if ($is_security_developer) { ?>
	<div class="row">
		<div class="col-xs-1">
		</div>
		<div class="col-xs-3">
			<label for="private">only marked as <b>private</b>
		</div>
		<div class="col-xs-4">
			<input type="checkbox" id="private" name="private" value="Y" <?php echo $private == 'Y' ? " checked" : "" ?>></label>
		</div>
		<div class="col-xs-4">
		</div>
	</div>
	<?php } ?>
</div>
</form>

<?php
response_footer();

function show_prev_next($begin, $rows, $total_rows, $link, $limit)
{
	echo "<!-- BEGIN PREV/NEXT -->\n";
	echo " <tr>\n";
	echo '  <td class="search-prev_next" colspan="11">' . "\n";

	if ($limit=='All') {
		echo "$total_rows Bugs</td></tr>\n";
		return;
	}

	echo '   <table border="0" cellspacing="0" cellpadding="0" width="100%">' . "\n";
	echo "	<tr>\n";
	echo '    <td class="search-prev">';
	if ($begin > 0) {
		echo '<a href="' . $link . '&amp;begin=';
		echo max(0, $begin - $limit);
		echo '">&laquo; Show Previous ' . $limit . ' Entries</a>';
	} else {
		echo '&nbsp;';
	}
	echo "</td>\n";

	echo '   <td class="search-showing">Showing ' . ($begin+1);
	echo '-' . ($begin+$rows) . ' of ' . $total_rows . "</td>\n";

	echo '   <td class="search-next">';
	if ($begin+$rows < $total_rows) {
		echo '<a href="' . $link . '&amp;begin=' . ($begin+$limit);
		echo '">Show Next ' . $limit . ' Entries &raquo;</a>';
	} else {
		echo '&nbsp;';
	}
	echo "</td>\n	</tr>\n   </table>\n  </td>\n </tr>\n";
	echo "<!-- END PREV/NEXT -->\n";
}

function show_order_options($current)
{
	global $order_options;

	foreach ($order_options as $k => $v) {
		echo '<option value="', $k, '"', ($v == $current ? ' selected="selected"' : ''), '>Sort by ', $v, "</option>\n";
	}
}
