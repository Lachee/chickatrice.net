<?php
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

?>

<section class="hero is-medium is-dark is-bold is-fullheight-with-navbar">
  <div class="hero-body">
    <div class="container">
      <h1 class="title is-size-1">JWT</h1>
      <h2 class="subtitle is-size-3">Public Json Web Token secret</h2>
      <div class="content">
        <a class="button is-dark is-inverted is-outlined is-large" href="https://jwt.io/" target="_blank">
          <span class="icon"><img src="http://jwt.io/img/icon.svg"></span>
          <span>More Information</span>
        </a>
      </div>
      <div class="content">
        <p>
        <?= Kiss::$app->title ?> is based of a custom framework called KISS. This utilises JWT (Json Web Tokens) for all its sessioning data.
          These are a standard (RFC 7519) way to encode JSON data as a secure token. 

          Some components of <?= Kiss::$app->title ?> will pass back a JWT (for example Webhooks). 
          It is important for these components that you are able to verify the validity of these tokens.
        </p>
        <p>          
          It is up to the implementor of API to ensure the web tokens are from us.<br>
          You can do this by using the Public Key listed below. All JWTs from this site are generated using a RSA keypair, and you can use this public key to decode it.
        </p>
      </div>
        <pre><?= $key ?></pre>
    </div>
  </div>
</section>