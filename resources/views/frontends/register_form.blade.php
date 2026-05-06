<div class="main-register-wrap modal">
    <div class="reg-overlay"></div>
    <div class="main-register-holder tabs-act">
        <div class="main-register-wrapper modal_main fl-wrap">
            <div class="main-register-header color-bg">
                <div class="main-register-logo fl-wrap">
                    <img src="images/logo1.svg" alt="">
                </div>
                <div class="main-register-bg">
                    <div class="mrb_pin"></div>
                    <div class="mrb_pin mrb_pin2"></div>
                </div>
                <div class="mrb_dec"></div>
                <div class="mrb_dec mrb_dec2"></div>
            </div>
            <div class="main-register">
                <div class="close-reg"><i class="fal fa-times"></i></div>
                <ul class="tabs-menu fl-wrap no-list-style">
                    <li class="current"><a href="#tab-1"><i class="fal fa-sign-in-alt"></i> Đăng nhập</a></li>
                    <li><a href="#tab-2"><i class="fal fa-user-plus"></i> Đăng ký</a></li>
                </ul>
                <!--tabs -->
                <div class="tabs-container">
                    <div class="tab">
                        <!--tab -->
                        <div id="tab-1" class="tab-content first-tab">
                            <div class="custom-form">
                                <form method="post" name="registerform">
                                    <label>Email đăng nhập * <span class="dec-icon"><i
                                                class="fal fa-user"></i></span></label>
                                    <input name="email" type="text" placeholder="Your Name or Mail"
                                        onClick="this.select()" value="">
                                    <div class="pass-input-wrap fl-wrap">
                                        <label>Mật khẩu * <span class="dec-icon"><i
                                                    class="fal fa-key"></i></span></label>
                                        <input name="password" placeholder="Your Password" type="password"
                                            autocomplete="off" onClick="this.select()" value="">
                                        <span class="eye"><i class="fal fa-eye"></i> </span>
                                    </div>
                                    <div class="lost_password">
                                        <a href="#">Quên mật khẩu?</a>
                                    </div>
                                    <div class="filter-tags">
                                        <input id="check-a3" type="checkbox" name="check">
                                        <label for="check-a3">Duy trì kết nối</label>
                                    </div>
                                    <div class="clearfix"></div>
                                    <button type="submit" class="log_btn color-bg"> Đăng nhập </button>
                                </form>
                            </div>
                        </div>
                        <!--tab end -->
                        <!--tab -->
                        <div class="tab">
                            <div id="tab-2" class="tab-content">
                                <div class="custom-form">
                                    <form method="post" name="registerform" class="main-register-form"
                                        id="main-register-form2">
                                        <label>Họ và Tên * <span class="dec-icon"><i
                                                    class="fal fa-user"></i></span></label>
                                        <input name="name" type="text" placeholder="Họ và Tên" onClick="this.select()"
                                            value="">
                                        <label>Địa chỉ Email * <span class="dec-icon"><i
                                                    class="fal fa-envelope"></i></span></label>
                                        <input name="email" type="text" placeholder="Địa chỉ Email" onClick="this.select()"
                                            value="">
                                        <div class="pass-input-wrap fl-wrap">
                                            <label>Mật khẩu * <span class="dec-icon"><i
                                                        class="fal fa-key"></i></span></label>
                                            <input name="password" type="password" placeholder="Mật khẩu"
                                                autocomplete="off" onClick="this.select()" value="">
                                            <span class="eye"><i class="fal fa-eye"></i> </span>
                                        </div>
                                        <div class="filter-tags ft-list">
                                            <input id="check-a2" type="checkbox" name="check">
                                            <label for="check-a2">Tôi đã đọc và đồng ý các điểu khoản <a href="#">Điểu khoản</a> và <a
                                                    href="#">Chính sách</a></label>
                                        </div>
                                        <div class="clearfix"></div>
                                        <button type="submit" class="log_btn color-bg"> Đăng ký </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!--tab end -->
                    </div>
                    <!--tabs end -->
                    <div class="log-separator fl-wrap"><span>or</span></div>
                    <div class="soc-log fl-wrap">
                        <p>Để đăng nhập hoặc đăng ký nhanh hơn, hãy sử dụng tài khoản mạng xã hội của bạn.</p>
                        <a href="#" class="facebook-log"> Google</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
