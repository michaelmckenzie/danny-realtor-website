<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/v2/" class="site-logo footer-logo">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16 2L2 14H6V28H13V20H19V28H26V14H30L16 2Z" fill="currentColor"/>
          </svg>
          <span>Danny<strong>Homes</strong></span>
        </a>
        <p class="footer-tagline">Your local real estate expert.</p>
        <p class="footer-contact">
          <strong><?= e(AGENT_NAME) ?></strong><br>
          <a href="tel:<?= e(AGENT_PHONE) ?>"><?= e(AGENT_PHONE) ?></a><br>
          <a href="mailto:<?= e(AGENT_EMAIL) ?>"><?= e(AGENT_EMAIL) ?></a>
        </p>
      </div>

      <div class="footer-links-col">
        <h4>Buy</h4>
        <ul>
          <li><a href="/v2/listings.php?type=sale">Homes for Sale</a></li>
          <li><a href="/v2/listings.php?type=sale&property_type=Single+Family">Single Family</a></li>
          <li><a href="/v2/listings.php?type=sale&property_type=Condo">Condos &amp; Apartments</a></li>
          <li><a href="/v2/listings.php?type=sale&property_type=Townhouse">Townhouses</a></li>
        </ul>
      </div>

      <div class="footer-links-col">
        <h4>Rent</h4>
        <ul>
          <li><a href="/v2/listings.php?type=rent">Rentals</a></li>
          <li><a href="/v2/listings.php?type=rent&property_type=Single+Family">Houses</a></li>
          <li><a href="/v2/listings.php?type=rent&property_type=Apartment">Apartments</a></li>
        </ul>
      </div>

      <div class="footer-links-col">
        <h4>More</h4>
        <ul>
          <li><a href="/v2/listings.php">All Listings</a></li>
          <li><a href="mailto:<?= e(AGENT_EMAIL) ?>">Contact Agent</a></li>
          <li><a href="/v2/admin/">Admin Portal</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
      <p class="footer-disclaimer">
        All listing information is deemed reliable but not guaranteed.
        Equal Housing Opportunity.
      </p>
    </div>
  </div>
</footer>

<script src="/v2/assets/js/main.js"></script>
<script>
(function(){
  var v='b', start=Date.now();
  function track(event,extra){
    var body='event='+event+'&v='+v+(extra?'&'+extra:'');
    if(navigator.sendBeacon){navigator.sendBeacon('/track.php',body);}
    else{var x=new XMLHttpRequest();x.open('POST','/track.php',true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send(body);}
  }
  track('pageview');
  document.querySelectorAll('a[href^="tel:"],a[href^="mailto:"]').forEach(function(el){
    el.addEventListener('click',function(){track('contact');});
  });
  window.addEventListener('beforeunload',function(){
    var secs=Math.round((Date.now()-start)/1000);
    track('exit','time='+secs);
  });
})();
</script>
</body>
</html>
