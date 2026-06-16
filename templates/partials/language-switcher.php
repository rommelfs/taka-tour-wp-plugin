<?php
/**
 * Language switcher partial.
 */

defined( 'ABSPATH' ) || exit;

$current = taka_tour_current_language();
$labels  = Taka_Tour_Translator::language_labels();
?>
<nav class="taka-language-switcher" aria-label="<?php echo esc_attr( taka_tour_translate( 'language.switcher_label', 'Sprache wählen' ) ); ?>">
	<?php foreach ( $labels as $code => $label ) : ?>
		<?php $url = add_query_arg( 'taka_lang', $code ); ?>
		<a class="taka-language-switcher__link<?php echo $current === $code ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" lang="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
