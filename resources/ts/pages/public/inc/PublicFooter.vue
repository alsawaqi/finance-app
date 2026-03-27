<template>
    <footer class="main_footer">
      <div class="footer_top">
        <div class="container">
          <div class="row">
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-7 col-xs-12">
              <div class="footer_widget about_widget aos-init" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="500">
                <figure class="footer_logo">
                  <RouterLink to="/">
                    <img src="/financer/assets/images/footer-logo.png" alt="" />
                  </RouterLink>
                </figure>
                <p>The future is fast approaching, and the consumer industry is on the precipice of dramatic change</p>
                <ul class="social-links">
                  <li><a href="#" @click.prevent><i class="icon-43"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-42"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-40"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-41"></i></a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-5 col-xs-12">
              <div class="footer_widget page_widget aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="550">
                <h4 class="footer_widget_title">Pages</h4>
                <ul class="page_list">
                  <li><RouterLink to="/about">About us</RouterLink></li>
                  <li><a href="#" @click.prevent>Blogs</a></li>
                  <li><a href="#" @click.prevent>FAQ’s</a></li>
                  <li><a href="#" @click.prevent>Contact</a></li>
                  <li><a href="#" @click.prevent>Support</a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-5 col-xs-12">
              <div class="footer_widget primary_page_widget aos-init" data-aos="fade-down" data-aos-easing="linear" data-aos-duration="600">
                <h4 class="footer_widget_title">Primary Pages</h4>
                <ul class="primary_page_list">
                  <li><a href="#" @click.prevent>Services</a></li>
                  <li><a href="#" @click.prevent>Careers</a></li>
                  <li><a href="#" @click.prevent>Pricing Plans</a></li>
                  <li><a href="#" @click.prevent>Portfolio</a></li>
                  <li><a href="#" @click.prevent>News</a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-7 col-xs-12">
              <div class="footer_widget newsletter_widget aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="650">
                <h4 class="footer_widget_title">Subscribe Newsletter</h4>
                <p>To add complexity, this is happening against a back drop of significant challenges</p>
                <div class="subscribe-inner">
                  <form class="subscribe-form" @submit.prevent>
                    <div class="form-group">
                      <input type="email" name="email" placeholder="Email Address" />
                      <button type="submit" class="btn_style_one">Subscribe</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <div class="footer_bottom">
        <div class="container">
          <div class="copyright">
            Copyright &copy; 2024 &nbsp;<RouterLink to="/">Financer</RouterLink> , Inc. All Rights Reserved
          </div>
        </div>
      </div>
  
      <div class="footer_shape"></div>
    </footer>
</template>