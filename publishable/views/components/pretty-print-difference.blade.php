<?php
use \Jfcherng\Diff\DiffHelper;
?>

<style>
    @php DiffHelper::getStyleSheet(); @endphp
</style>

<div>
    {!! $content !!}
</div>