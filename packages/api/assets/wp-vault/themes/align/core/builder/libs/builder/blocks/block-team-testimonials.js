/**
 * Align Builder Block Templates - Quotes & Newsletters
 * @package Align
 */

Vvveb.Blocks.add("bootstrap4/block-quote", {
    name: "Block quote",
    image: "https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/d9f382e143b77d5a630dd79a2a3860611a8a953c.jpg",
    html:`
<div class="container">
    <blockquote class="quote-box">
      <p class="quotation-mark">
        “
      </p>
      <p class="quote-text">
        Don't believe anything that you read on the internet, it may be fake. 
      </p>
      <hr>
      <div class="blog-post-actions">
        <p class="blog-post-bottom pull-left">
          Abraham Lincoln
        </p>
        <p class="blog-post-bottom pull-right">
          <span class="badge quote-badge">896</span>  ❤
        </p>
      </div>
    </blockquote>
<style>
blockquote{
    border-left:none
}

.quote-badge{
    background-color: rgba(0, 0, 0, 0.2);   
}

.quote-box{
    
    overflow: hidden;
    margin-top: -50px;
    padding-top: -100px;
    border-radius: 17px;
    background-color: #4ADFCC;
    margin-top: 25px;
    color:white;
    width: 325px;
    box-shadow: 2px 2px 2px 2px #E0E0E0;
    
}

.quotation-mark{
    
    margin-top: -10px;
    font-weight: bold;
    font-size:100px;
    color:white;
    font-family: "Times New Roman", Georgia, Serif;
    
}

.quote-text{
    
    font-size: 19px;
    margin-top: -65px;
}
</style>
</div>
`,
});

Vvveb.Blocks.add("bootstrap4/subscribe-newsletter", {
    name: "Subscribe newsletter",
    image: "https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/4f610196b7cb9596555c9c8c475d93ab4ef084f6.jpg",
    html:`
<div class="subscribe-area pb-50 pt-70">
<div class="container">
	<div class="row">

					<div class="col-md-4">
						<div class="subscribe-text mb-15">
							<span>JOIN OUR NEWSLETTER</span>
							<h2>subscribe newsletter</h2>
						</div>
					</div>
					<div class="col-md-8">
						<div class="subscribe-wrapper subscribe2-wrapper mb-15">
							<div class="subscribe-form">
								<form action="#">
									<input placeholder="enter your email address" type="email">
									<button>subscribe <i class="fas fa-long-arrow-alt-right"></i></button>
								</form>
							</div>
						</div>
					</div>
				</div>

</div>
<style>
.subscribe-area {
background-image: linear-gradient(to top, #00c6fb 0%, #005bea 100%);
}

.pb-50 {
    padding-bottom: 50px;
}
.pt-70 {
    padding-top: 70px;
}

.mb-15 {
    margin-bottom: 15px;
}

.subscribe-text span {
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 5px;
}
.subscribe-text h2 {
    color: #fff;
    font-size: 36px;
    font-weight: 300;
    margin-bottom: 0;
    margin-top: 6px;
}
.subscribe-wrapper {
    overflow: hidden;
}
.mb-15 {
    margin-bottom: 15px;
}
.subscribe-form {
}
.subscribe2-wrapper .subscribe-form input {
    background: none;
    border: 1px solid #fff;
    border-radius: 30px;
    color: #fff;
    display: inline-block;
    font-size: 15px;
    font-weight: 300;
    height: 57px;
    margin-right: 17px;
    padding-left: 35px;
    width: 70%;
    cursor: pointer;
}
 
.subscribe2-wrapper .subscribe-form button {
    background: #ffff;
    border: none;
    border-radius: 30px;
    color: #4b5d73;
    display: inline-block;
    font-size: 18px;
    font-weight: 400;
    line-height: 1;
    padding: 18px 46px;
    transition: all 0.3s ease 0s;
}
.subscribe2-wrapper .subscribe-form button i {
    font-size: 18px;
    padding-left: 5px;
}
</style>
</div>
`,
});
