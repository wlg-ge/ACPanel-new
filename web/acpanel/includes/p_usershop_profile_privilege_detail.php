<?php

	$id = trim($_GET['id']);
	if( !is_numeric($id) ) die("Hacking Attempt");

	$time = time();
	$sqlconds = "";
	$product_GA = getProduct("gameAccounts");

	header('Content-type: text/html; charset='.$config['charset']);

	$arguments = array('id'=>$id, 'uid'=>$userinfo['uid']);
	$sqlconds_select = $sqlconds_join = "";
	if( !empty($product_GA) )
	{
		$sqlconds_select .= ", e.access_flags, GROUP_CONCAT(f.server_id SEPARATOR ',') AS servers";
		$sqlconds_join .= " LEFT JOIN `acp_access_mask` e ON e.mask_id = a.add_mask_id LEFT JOIN `acp_access_mask_servers` f ON f.mask_id = e.mask_id";
	}

	$result = $db->Query("SELECT a.id, a.uid, a.pattern_id, a.date_start, a.date_end, a.add_mask_id, a.new_group, 
		b.usergroupname, c.name, c.description".$sqlconds_select."
		FROM `acp_payment_user` a 
		LEFT JOIN `acp_usergroups` b ON b.usergroupid = a.new_group
		LEFT JOIN `acp_payment_patterns` c ON c.id = a.pattern_id
		".$sqlconds_join."
		WHERE a.id = '{id}' AND a.uid = '{uid}' GROUP BY a.id
	", $arguments, $config['sql_debug']);

	if( is_array($result) )
	{
		foreach( $result as $obj )
		{
			if( !empty($product_GA) )
			{
				if( $priv['account_mask'] = (!is_null($obj->access_flags) && $obj->add_mask_id > 0) ? $obj->access_flags : "" )
				{
					if( is_null($obj->servers) )
					{
						$priv['mask_servers'] = "";
					}
					else
					{
						if( $obj->servers == 0 )
						{
							$priv['mask_servers'] = "@@all_servers@@";
						}
						else
						{
							$result_servers = $db->Query("SELECT address, hostname FROM `acp_servers` WHERE id IN(".$obj->servers.")", array(), $config['sql_debug']);
							if( is_array($result_servers) )
							{
								$priv['mask_servers'] = "<ul>";
								foreach( $result_servers as $objsrv )
								{
									$priv['mask_servers'] .= "<li>".htmlspecialchars($objsrv->hostname)." (".htmlspecialchars($objsrv->address).")</li>";
								}
								$priv['mask_servers'] .= "</ul>";
							}
							else
							{
								$priv['mask_servers'] = "";
							}
						}
					}
				}
			}

			$priv['privilege'] = ( !$obj->name ) ? '<span style="text-decoration:line-through;">@@payment_pattern_deleted@@</span>' : htmlspecialchars($obj->name);
			$priv['description'] = ( !$obj->name ) ? '' : htmlspecialchars($obj->description);
			if( !$obj->new_group )
				$priv['group'] = "";
			else
			{
				$priv['group'] = ( !$obj->usergroupname ) ? '<span style="text-decoration:line-through;">@@usergroup_removed@@</span>' : htmlspecialchars($obj->usergroupname);
			}

			$date_start = ($obj->date_start > 0) ? get_datetime($obj->date_start, $config['date_format']) : "?";
			$date_end = ($obj->date_end > 0) ? get_datetime($obj->date_end, $config['date_format']) : "<span class='infinity'></span>";
			$time_expired = ($obj->date_end > 0 && $time > $obj->date_end) ? "@@time_expired@@" : ((!$obj->date_end) ? "@@time_expired_permanent@@" : "@@time_expired_pre@@: ".compacttime(($obj->date_end - $time), "dddd hhhh"));
			if( $obj->date_end > 0 && $time > $obj->date_end )
				 $smarty->assign("time_expired", true);
			$priv['lifetime'] = $date_start." - ".$date_end." (".$time_expired.")";
			$priv['id'] = $obj->id;
		}
	}

	if(isset($priv)) $smarty->assign("priv",$priv);

	$smarty->registerFilter("output","translate_template");
	$smarty->display('profile_shop_privileges_detail.tpl');

	exit;

?>