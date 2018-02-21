<?php if (!isset($_SERVER['HTTP_DNT'])){ ?>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?=GA_TRACKING_CODE?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?=GA_TRACKING_CODE?>');</script>
<?php } ?>
