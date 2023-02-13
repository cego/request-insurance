<?php
use Jfcherng\Diff\DiffHelper;
?>

<style>
  <?= DiffHelper::getStyleSheet(); ?>
</style>

<pre>{!! $content !!}</pre>
