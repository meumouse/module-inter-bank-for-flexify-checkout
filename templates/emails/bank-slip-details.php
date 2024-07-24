<?php

defined('ABSPATH') || exit;

?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td align="center">
			<table width="200" height="44" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo $color; ?>" style="border-radius:4px;">
				<tr>
					<td class="p0" align="center" valign="middle" height="44" style="font-family: Arial, sans-serif; font-size:14px; font-weight:bold;">
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" style="font-family: Arial, sans-serif; color:#ffffff; display: inline-block; text-decoration: none; line-height:44px; height:44px; display: block; width:200px; font-weight:bold; letter-spacing:1px;"><?php echo esc_html( $label ); ?></a>
					</td>
				</tr>
			</table>

			<table width="100%" cellpadding="0" cellspacing="0">
        <tr>
					<td class="p0" align="center" valign="middle">
            <h4 style="font-family: Arial, sans-serif; font-size:14px; font-weight:bold;"><?php echo __( 'Linha digitável', 'module-inter-bank-for-flexify-checkout' ); ?></h4>
            <pre style="padding: 10px; border: 1px solid #ccc; text-align: center"><?php echo $inter_payment_line; ?></pre>
					</td>
				</tr>
			</table>
		</td>
    </tr>
    <tr>
		<td class="p0" height="12" style="font-size:12px; line-height:12px;">&nbsp;</td>
	</tr>
</table>
<style>
	.p0 {
		padding: 0!important;
	}
</style>
