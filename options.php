<?php 
if (is_user_logged_in() && is_admin()) {
	
	$adminSettings = $this->defaultOptions;

	if (isset($_POST['update-splg_options'])) {//save option changes
		foreach ($adminSettings as $key => $val){
			$adminSettings[$key] = trim($_POST[$key]);
		}
	
		update_option('splg_options', $adminSettings);
	}
	
	$adminOptions = $this->getAdminOptions();
	

?>

<div class="wrap">
  <?php 
  screen_icon(); 
  ?>
  <form action="options-general.php?page=splg_options&saved" method="post" id="splg_options_form" name="splg_options_form">
  <?php wp_nonce_field('splg_options'); ?>
  <h2>Spreadplugin Plugin Options &raquo; Settings</h2>
  <div id="message" class="updated fade" style="display:none"></div>
  <h3>
    <?php _e('Settings','spreadplugin'); ?>
  </h3>
  <p>
    <?php _e('These settings will be used as default and can be overwritten by the extended shortcode.','spreadplugin'); ?>
  </p>
  <table border="0" cellpadding="3" cellspacing="0">
    <tr>
      <td valign="top"><?php _e('Shop id:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_id" value="<?php echo (empty($adminOptions['shop_id'])?0:$adminOptions['shop_id']); ?>" class="only-digit" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Shop locale:','spreadplugin'); ?></td>
      <td><select name="shop_locale" id="shop_locale">
          <option value="de_DE"<?php echo ($adminOptions['shop_locale']=='de_DE' || empty($adminOptions['shop_locale'])?" selected":"") ?>>Deutschland</option>
          <option value="fr_FR"<?php echo ($adminOptions['shop_locale']=='fr_FR'?" selected":"") ?>>France</option>
          <option value="en_GB"<?php echo ($adminOptions['shop_locale']=='en_GB'?" selected":"") ?>>United Kingdom</option>
          <option value="nl_BE"<?php echo ($adminOptions['shop_locale']=='nl_BE'?" selected":"") ?>>Belgie (Nederlands)</option>
          <option value="fr_BE"<?php echo ($adminOptions['shop_locale']=='fr_BE'?" selected":"") ?>>Belgique (Fran&ccedil;ais)</option>
          <option value="dk_DK"<?php echo ($adminOptions['shop_locale']=='dk_DK'?" selected":"") ?>>Danmark</option>
          <option value="es_ES"<?php echo ($adminOptions['shop_locale']=='es_ES'?" selected":"") ?>>Espa&ntilde;a</option>
          <option value="en_IE"<?php echo ($adminOptions['shop_locale']=='en_IE'?" selected":"") ?>>Ireland</option>
          <option value="it_IT"<?php echo ($adminOptions['shop_locale']=='it_IT'?" selected":"") ?>>Italia</option>
          <option value="nl_NL"<?php echo ($adminOptions['shop_locale']=='nl_NL'?" selected":"") ?>>Nederland</option>
          <option value="no_NO"<?php echo ($adminOptions['shop_locale']=='no_NO'?" selected":"") ?>>Norge</option>
          <option value="pl_PL"<?php echo ($adminOptions['shop_locale']=='pl_PL'?" selected":"") ?>>Polska</option>
          <option value="fi_FI"<?php echo ($adminOptions['shop_locale']=='fi_FI'?" selected":"") ?>>Suomi</option>
          <option value="se_SE"<?php echo ($adminOptions['shop_locale']=='se_SE'?" selected":"") ?>>Sverige</option>
          <option value="de_AT"<?php echo ($adminOptions['shop_locale']=='de_AT'?" selected":"") ?>>&Ouml;sterreich</option>
          <option value="us_US"<?php echo ($adminOptions['shop_locale']=='us_US'?" selected":"") ?>>United States</option>
          <option value="us_CA"<?php echo ($adminOptions['shop_locale']=='us_CA'?" selected":"") ?>>Canada (English)</option>
          <option value="fr_CA"<?php echo ($adminOptions['shop_locale']=='fr_CA'?" selected":"") ?>>Canada (Fran&ccedil;ais)</option>
        </select></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Spreadshirt API Key:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_api" value="<?php echo $adminOptions['shop_api']; ?>" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Spreadshirt API Secret:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_secret" value="<?php echo $adminOptions['shop_secret']; ?>" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Shop source:','spreadplugin'); ?></td>
      <td><select name="shop_source" id="shop_source">
          <option value="net"<?php echo ($adminOptions['shop_source']=='net'?" selected":"") ?>>Europe</option>
          <option value="com"<?php echo ($adminOptions['shop_source']=='com'?" selected":"") ?>>US/Canada</option>
        </select></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Limit articles:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_limit" value="<?php echo (empty($adminOptions['shop_limit'])?20:$adminOptions['shop_limit']); ?>" class="only-digit" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Product category:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_productcategory" value="<?php echo $adminOptions['shop_productcategory']; ?>" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Article category:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_category" value="<?php echo $adminOptions['shop_category']; ?>" class="only-digit" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Social plugins:','spreadplugin'); ?></td>
      <td><input type="radio" name="shop_social" value="0"<?php echo ($adminOptions['shop_social']==0?" checked":"") ?> />
        <?php _e('Disabled','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_social" value="1"<?php echo ($adminOptions['shop_social']==1?" checked":"") ?> />
        <?php _e('Enabled','spreadplugin'); ?></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Product linking:','spreadplugin'); ?></td>
      <td><input type="radio" name="shop_enablelink" value="0"<?php echo ($adminOptions['shop_enablelink']==0?" checked":"") ?> />
        <?php _e('Disabled','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_enablelink" value="1"<?php echo ($adminOptions['shop_enablelink']==1?" checked":"") ?> />
        <?php _e('Enabled','spreadplugin'); ?></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Default sorting:','spreadplugin'); ?></td>
      <td><select name="shop_sortby" id="shop_sortby">
          <option></option>
          <?php if (!empty(self::$shopArticleSortOptions)) {
		  foreach (self::$shopArticleSortOptions as $val) {
			  ?>
          <option value="<?php echo $val; ?>"<?php echo ($adminOptions['shop_sortby']==$val?" selected":"") ?>><?php echo $val; ?></option>
          <?php }
	  }
	  ?>
        </select></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Target of links:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_linktarget" value="<?php echo ($adminOptions['shop_linktarget']?'_blank':$adminOptions['shop_linktarget']); ?>" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Use iframe for checkout:','spreadplugin'); ?></td>
      <td><input type="radio" name="shop_checkoutiframe" value="0"<?php echo ($adminOptions['shop_checkoutiframe']==0?" checked":"") ?> />
        <?php _e('Opens in separate window','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_checkoutiframe" value="1"<?php echo ($adminOptions['shop_checkoutiframe']==1?" checked":"") ?> />
        <?php _e('Opens an iframe in the page content','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_checkoutiframe" value="2"<?php echo ($adminOptions['shop_checkoutiframe']==2?" checked":"") ?> />
        <?php _e('Opens an iframe in a modal window (fancybox)','spreadplugin'); ?></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Designer Shop id:','spreadplugin'); ?></td>
      <td><input type="text" name="shop_designershop" value="<?php echo $adminOptions['shop_designershop']; ?>" class="only-digit" /></td>
    </tr>
    <tr>
      <td valign="top"><?php _e('Default display:','spreadplugin'); ?></td>
      <td><input type="radio" name="shop_display" value="0"<?php echo ($adminOptions['shop_display']==0?" checked":"") ?> />
        <?php _e('Articles','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_display" value="1"<?php echo ($adminOptions['shop_display']==1?" checked":"") ?> />
        <?php _e('Designs','spreadplugin'); ?></td>
    </tr>
    <!-- 
    <tr>
      <td valign="top"><?php _e('Designs with background:','spreadplugin'); ?></td>
      <td><input type="radio" name="shop_designsbackground" value="0"<?php echo ($adminOptions['shop_designsbackground']==0?" checked":"") ?> />
        <?php _e('Disabled','spreadplugin'); ?>
        <br />
        <input type="radio" name="shop_designsbackground" value="1"<?php echo ($adminOptions['shop_designsbackground']==1?" checked":"") ?> />
        <?php _e('Enabled','spreadplugin'); ?></td>
    </tr>
    -->
  </table>
  <br />
  <input type="submit" name="update-splg_options" id="update-splg_options" value="<?php _e('Update settings','spreadplugin'); ?>" />
  <p>&nbsp;</p>
  <h4>
    <?php _e('Minimum required shortcode','spreadplugin'); ?>
  </h4>
  <p>[spreadplugin]</p>
  <h4>
    <?php _e('Extended sample shortcode','spreadplugin'); ?>
  </h4>
  <p>
    <?php _e('The extended shortcodes will overwrite the default settings.'); ?>
  </p>
  <p><strong>US/NA</strong><br />
    [spreadplugin shop_id=&quot;414192&quot; shop_limit=&quot;20&quot; shop_locale=&quot;us_US&quot; shop_source=&quot;com&quot; shop_category=&quot;&quot; shop_social=&quot;1&quot; shop_enablelink=&quot;1&quot; shop_productcategory=&quot;&quot; shop_checkoutiframe=&quot;2&quot; shop_sortby=&quot;&quot; shop_designershop=&quot;0&quot; shop_display=&quot;0&quot; shop_api=&quot;&quot; shop_secret=&quot;&quot;]</p>
  <p><strong>EU (DE,...)</strong><br />
    [spreadplugin shop_id=&quot;732552&quot; shop_limit=&quot;20&quot; shop_locale=&quot;de_DE&quot; shop_source=&quot;net&quot; shop_category=&quot;&quot; shop_social=&quot;1&quot; shop_enablelink=&quot;1&quot; shop_productcategory=&quot;&quot; shop_checkoutiframe=&quot;2&quot; shop_sortby=&quot;&quot; shop_designershop=&quot;0&quot; shop_display=&quot;0&quot; shop_api=&quot;&quot; shop_secret=&quot;&quot;]<br />
  </p>
  <p><br />
  </p>
  <h3>
    <?php _e('Options','spreadplugin'); ?>
  </h3>
  <p><a href="javascript:;" onclick="rebuild();"><strong>
    <?php _e('Clear cache','spreadplugin'); ?>
    </strong></a></p>
  <p>&nbsp;</p>
  <p>If you like this plugin, I'd be happy to read your comments on <a href="http://www.facebook.com/pr3ss.play" target="_blank">facebook</a>. 
    If you experience any problems or have suggestions, feel free to leave a message on <a href="http://wordpress.org/support/plugin/wp-spreadplugin" target="_blank">wordpress</a> or send an email to <a href="mailto:info@spreadplugin.de">info@spreadplugin.de</a>.<br />
  </p>
  <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
    <input type="hidden" name="cmd" value="_s-xclick">
    <input type="hidden" name="hosted_button_id" value="EZLKTKW8UR6PQ">
    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="Jetzt einfach, schnell und sicher online bezahlen � mit PayPal.">
    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
  </form>
  <p>All donations or backlinks to <a href="http://www.pr3ss-play.de/" target="_blank">http://www.pr3ss-play.de/</a> valued greatly</p>
</div>
<script language="javascript">
	function setMessage(msg) {
		jQuery("#message").append(msg); //.html(msg)
		jQuery("#message").show();
	}

	function rebuild() {
		jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>","action=regenCache", function() {
			setMessage("<p><?php _e('Successfully cleared the cache','spreadplugin'); ?></p>");
		});
	}
	
	jQuery('.only-digit').keyup(function() {
		if (/\D/g.test(this.value)) {
			// Filter non-digits from input value.
			this.value = this.value.replace(/\D/g, '');
		}
	});

	// select different locale if north america is set
	jQuery('#shop_locale').change(function() {
		var sel = jQuery(this).val();

		if (sel == 'us_US' || sel == 'us_CA' || sel == 'fr_CA') {
			jQuery('#shop_source').val('com');
		} else {
			jQuery('#shop_source').val('net');
		}
	});
	
</script>
<?php 
if (isset($_GET['saved'])) {
	echo '<script language="javascript">rebuild();</script>';
	echo '<script language="javascript">setMessage("<p>'.__('Successfully saved settings','spreadplugin').'</p>");</script>';
}


} ?>
