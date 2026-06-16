    <div id="sidebar" class="active">
        <div class="sidebar-wrapper active">
            <div class="sidebar-header position-relative">
                <div class="d-flex ">
                    <div class="logo">
                        <a href="{{ url('home') }}">
                            <img src="{{ url('assets/images/logo/logo.png') }}?v={{ @filemtime(public_path('assets/images/logo/logo.png')) }}" alt="Logo" srcset="">
                        </a>
                    </div>
                    &nbsp;
                    <p class='text-center'>
                        {{ config('app.name') }}
                    </p>
                </div>
            </div>
            <div class="sidebar-menu">
                <ul class="menu">
                    @if (has_permissions('read', 'dashboard'))
                        <li class="sidebar-item">
                            <a href="{{ url('home') }}" class='sidebar-link'>
                                <i class="bi bi-grid-fill"></i>
                                <span class="menu-item">{{ __('Dashboard') }}</span>
                            </a>
                        </li>
                    @endif
                    {{-- Quản lý Bán hàng --}}
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-cart-fill"></i>
                            <span class="menu-item">{{ __('Quản lý Bán hàng') }}</span>
                        </a>
                        <ul class="submenu" style="padding-left: 0rem">
                            <li class="submenu-item">
                                <a href="{{ url('zalo-orders') }}">{{ __('Danh sách đơn hàng') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ route('order-packing.index') }}">{{ __('Phân công đóng gói') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ route('refunds.pending') }}" class="d-flex justify-content-between align-items-center">
                                    <span>{{ __('Hoàn tiền chờ xử lý') }}</span>
                                    @if(($pendingRefundCount ?? 0) > 0)
                                        <span class="badge bg-danger ms-2">{{ $pendingRefundCount }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('zalo-stations') }}">{{ __('Trạm lấy hàng') }}</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Quản lý Sản phẩm --}}
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-box-seam"></i>
                            <span class="menu-item">{{ __('Quản lý Sản phẩm') }}</span>
                        </a>
                        <ul class="submenu" style="padding-left: 0rem">
                            <li class="submenu-item">
                                <a href="{{ url('zalo-products') }}">{{ __('Danh sách sản phẩm') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('zalo-categories') }}">{{ __('Danh mục sản phẩm') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ route('inventory.index') }}">{{ __('Quản lý tồn kho') }}</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Khách hàng và Đối tác --}}
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-people-fill"></i>
                            <span class="menu-item">{{ __('Khách hàng và Đối tác') }}</span>
                        </a>
                        <ul class="submenu" style="padding-left: 0rem">
                            <li class="submenu-item">
                                <a href="{{ url('zalo-customers') }}">{{ __('Danh sách khách hàng') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('affiliate-partners') }}">{{ __('Cộng tác viên') }}</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Quản lý Nông trại --}}
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-tree"></i>
                            <span class="menu-item">{{ __('Quản lý Nông trại') }}</span>
                        </a>
                        <ul class="submenu" style="padding-left: 0rem">
                            <li class="submenu-item">
                                <a href="{{ url('farms') }}">{{ __('Danh sách nông trại') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('farm-requests') }}">{{ __('Yêu cầu hợp tác nông trại') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('farm-payouts') }}">{{ __('Đối soát doanh thu nông trại') }}</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Quảng cáo và Khuyến mãi --}}
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-megaphone-fill"></i>
                            <span class="menu-item">{{ __('Quảng cáo và Khuyến mãi') }}</span>
                        </a>
                        <ul class="submenu" style="padding-left: 0rem">
                            <li class="submenu-item">
                                <a href="{{ url('vouchers') }}">{{ __('Mã giảm giá') }}</a>
                            </li>
                            <li class="submenu-item">
                                <a href="{{ url('banners') }}">{{ __('Quản lý ảnh quảng cáo') }}</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Cài đặt Hệ thống --}}
                    @if (has_permissions('read', 'users_accounts') ||
                            has_permissions('read', 'about_us') ||
                            has_permissions('read', 'privacy_policy') ||
                            has_permissions('read', 'terms_condition'))
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-gear"></i>
                                <span class="menu-item">{{ __('Cài đặt Hệ thống') }}</span>
                            </a>
                            <ul class="submenu" style="padding-left: 0rem">
                                @if (has_permissions('read', 'users_accounts'))
                                    <li class="submenu-item">
                                        <a href="{{ url('users') }}">{{ __('Tài khoản người dùng') }}</a>
                                    </li>
                                @endif
                                @if (has_permissions('read', 'system_settings'))
                                    <li class="submenu-item">
                                        <a href="{{ url('system-settings') }}">{{ __('Cài đặt hệ thống') }}</a>
                                    </li>
                                @endif
                                @if (has_permissions('read', 'privacy_policy'))
                                    <li class="submenu-item">
                                        <a href="{{ url('privacy-policy') }}">{{ __('Chính sách bảo mật') }}</a>
                                    </li>
                                @endif
                                @if (has_permissions('read', 'terms_condition'))
                                    <li class="submenu-item">
                                        <a href="{{ url('terms-conditions') }}">{{ __('Điều khoản sử dụng') }}</a>
                                    </li>
                                @endif
                                <li class="submenu-item">
                                    <a href="{{ route('policies.index') }}">{{ __('Chính sách (Mini App)') }}</a>
                                </li>
                            </ul>
                        </li>
                        {{-- <li class="sidebar-item">
                            <a href="{{ url('system_version') }}" class='sidebar-link'>
                                <i class="fas fa-cloud-download-alt"></i>
                                <span class="menu-item">{{ __('System Update') }}</span>
                            </a>
                        </li> --}}
                        @endif

                    {{-- HuyTBQ: Old code --}}
                    {{-- @if (has_permissions('read', 'categories') || has_permissions('read', 'bedroom'))

                        @if (has_permissions('read', 'unit'))
                            <li class="sidebar-item">
                                <a href="{{ url('parameters') }}" class='sidebar-link'>
                                    <i class="bi bi-x-diamond"></i>
                                    <span class="menu-item">{{ __('Facilities') }}</span>
                                </a>
                            </li>
                        @endif

                        @if (has_permissions('read', 'categories'))
                            <li class="sidebar-item">
                                <a href="{{ url('categories') }}" class='sidebar-link'>
                                    <i class="fas fa-align-justify"></i>
                                    <span class="menu-item">{{ __('Categories') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif
                    @if (has_permissions('read', 'unit'))
                        <li class="sidebar-item">
                            <a href="{{ url('outdoor_facilities') }}" class='sidebar-link'>
                                <i class="bi bi-geo-alt"></i>
                                <span class="menu-item">{{ __('near_by_places') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (has_permissions('read', 'customer'))
                        <li class="sidebar-item">
                            <a href="{{ url('customer') }}" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span class="menu-item">{{ __('Customer') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (has_permissions('read', 'property'))
                        <li class="sidebar-item">
                            <a href="{{ url('property') }}" class='sidebar-link'>
                                <i class="bi bi-building"></i>
                                <span class="menu-item">{{ __('Property') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (has_permissions('read', 'customer'))
                        <li class="sidebar-item">
                            <a href="{{ url('report-reasons') }}" class='sidebar-link'>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="feather feather-list">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                                <span class="menu-item">{{ __('Report Reasons') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (has_permissions('read', 'customer'))
                        <li class="sidebar-item">
                            <a href="{{ url('users_reports') }}" class='sidebar-link'>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="feather feather-alert-octagon">
                                    <polygon
                                        points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2">
                                    </polygon>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span class="menu-item">{{ __('Users Reports') }}</span>
                            </a>
                        </li>
                    @endif
                    <li class="sidebar-item">
                        <a href="{{ url('getChatList') }}" class='sidebar-link'>
                            <i class="bi bi-chat"></i>
                            <span class="menu-item">{{ __('Chat') }}</span>
                        </a>
                    </li>
                    @if (has_permissions('read', 'slider'))
                        <li class="sidebar-item">
                            <a href="{{ url('slider') }}" class='sidebar-link'>
                                <i class="bi bi-sliders"></i>
                                <span class="menu-item">{{ __('Slider') }}</span>
                            </a>
                        </li>
                    @endif
                    <li class="sidebar-item">
                        <a href="{{ url('article') }}" class='sidebar-link'>
                            <i class="bi bi-vector-pen"></i>
                            <span class="menu-item">{{ __('Article') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="{{ url('featured_properties') }}" class='sidebar-link'>
                            <i class="bi bi-badge-ad"></i>
                            <span class="menu-item">{{ __('Advertisements') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="{{ url('package') }}" class='sidebar-link'>

                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <path fill="#000" fill-rule="evenodd"
                                    d="M1.5 9A1.5 1.5 0 0 1 3 7.5h18A1.5 1.5 0 0 1 22.5 9v11a1.5 1.5 0 0 1-1.5 1.5H3A1.5 1.5 0 0 1 1.5 20V9ZM3 8.5a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h18a.5.5 0 0 0 .5-.5V9a.5.5 0 0 0-.5-.5H3Z"
                                    clip-rule="evenodd" />
                                <path fill="#000" fill-rule="evenodd"
                                    d="M9.77 10.556a.5.5 0 0 1 .517.034l5 3.5a.5.5 0 0 1 0 .82l-5 3.5A.5.5 0 0 1 9.5 18v-7a.5.5 0 0 1 .27-.444zm.73 1.404v5.08l3.628-2.54-3.628-2.54zM20 6H4V5h16v1zm-2-2.5H6v-1h12v1z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="menu-item">{{ __('Package') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="{{ url('get_user_purchased_packages') }}" class='sidebar-link'>

                            <i class="bi bi-person-check"></i>

                            <span class="menu-item">{{ __('Users Packages') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="{{ url('calculator') }}" class='sidebar-link'>
                            <i class="bi bi-calculator"></i>
                            <span class="menu-item">{{ __('Calculator') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="{{ url('payment') }}" class='sidebar-link'>
                            <i class="bi bi-cash"></i>
                            <span class="menu-item">{{ __('Payment') }}</span>
                        </a>
                    </li>
                    @if (has_permissions('read', 'notification'))
                        <li class="sidebar-item">
                            <a href="{{ url('notification') }}" class='sidebar-link'>
                                <i class="bi bi-bell"></i>
                                <span class="menu-item">{{ __('Notification') }}</span>
                            </a>
                        </li>
                    @endif --}}

                    

                    
                </ul>
            </div>
        </div>
    </div>

