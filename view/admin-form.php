<div class='wrap'>
  <div class='icon32' id='icon-options-general'><br/></div>
  <h2>Skydrive Hotlinking Options</h2>
  <!-- Plugin Options Form BEGIN -->
  <form method='post' action='options.php'>
    <?php
      settings_fields($skydrv_hl_settings_group);
      do_settings_sections($sections_id);
      echo $notice;
    ?>
    <p class='submit'>
      <input type='submit' class='button-primary'
             value='<?php echo __('Save Changes'); ?>' />
    </p>
  </form>
  <!-- Plugin Options Form END -->

  <div style='margin-top:15px;'>
    <p style='font-style: italic;font-weight: bold;color: #26779a;'>
      If you find the Skydrive Hotlinking plugin to be useful, consider donating.
      Thanks.</p>
    <form action='https://www.paypal.com/cgi-bin/webscr' method='post'>
      <input type='hidden' name='cmd' value='_s-xclick'>
      <input type='hidden' name='hosted_button_id' value='VV3FKPX8KEAE4'>
      <input type='image' name='submit' alt='donate via PayPal' border='0'
             src='https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif'>
      <img alt='' border='0' width='1' height='1'
           src='https://www.paypalobjects.com/en_US/i/scr/pixel.gif'>
    </form>
  </div>

</div>
