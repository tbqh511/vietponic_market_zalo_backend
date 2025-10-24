<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $table = 'propertys';

    protected $fillable = [
        'category_id',

        //HuyTBQ: Add address columns for property table
        'street_number',
        'street_code',
        'ward_code',
        'host_id',
        'commission',

        'title',
        'description',
        'address',
        'client_address',
        'propery_type',
        'price',
        'title_image',
        'state',
        'country',
        'state',
        'status',
        'total_click',
        'rentduration',
        'latitude',
        'longitude'

    ];
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    protected $appends = [
        'gallery',
        'legalimages'
    ];

    //HuyTBQ: Start add address coloumns for propertys table
    public function ward()
    {
        return $this->hasOne(LocationsWard::class, 'code', 'ward_code');
    }

    public function street()
    {
        return $this->hasOne(LocationsStreet::class, 'code', 'street_code');
    }
    //End HuyTBQ

    //HuyTBQ: Add host_id for propertys table
    public function host()
    {
        return $this->hasOne(CrmHost::class, 'id', 'host_id')->select('id', 'gender', 'name', 'contact', 'age', 'company', 'about');
    }

    //End HuyTBQ

    //HuyTBQ: Add function for customer
    public function agent()
    {
        return $this->hasOne(Customer::class, 'id', 'added_by')->select('name', 'profile', 'mobile');
    }
    //End HuyTBQ

    //HuyTBQ: get Code attribute
    public function getCodeAttribute()
    {
        $prefix = ($this->propery_type == 0) ? 'B' : 'T';
        $wardName = $this->ward->name ?? '';

        // Chuyển đổi tên phường thành định dạng mong muốn
        //$formattedWardName = $this->formatWardName($wardName);
        //return $prefix . '_' . $this->id . '_' . $formattedWardName;
        return $prefix . '_' . $this->id;
    }

    // Hàm chuyển đổi tên phường thành định dạng mong muốn
    // private function formatWardName($wardName)
    // {
    //     // Loại bỏ các khoảng trắng ở đầu và cuối chuỗi
    //     $wardName = trim($wardName);

    //     // Tách tên phường thành mảng dựa trên khoảng trắng
    //     $parts = explode(' ', $wardName);

    //     // Lấy ra hai ký tự đầu tiên của mỗi phần tử trong mảng và kết hợp chúng lại
    //     $formattedWardName = '';
    //     foreach ($parts as $part) {
    //         $formattedWardName .= substr($part, 0, 2);
    //     }

    //     return $formattedWardName;
    // }
    //End HuyTBQ

    //HuyTBQ: add function get Aera
    public function getAreaAttribute()
    {
        return $this->parameters->where('id', config('global.area'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    //HuyTBQ: get floor area
    public function getFloorAreaAttribute()
    {
        return $this->parameters->where('id', config('global.floor_area'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ

    // HuyTBQ: add function get Legal
    public function getLegalAttribute()
    {
        return $this->parameters->where('id', config('global.legal'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Direction
    public function getDirectionAttribute()
    {
        return $this->parameters->where('id', config('global.direction'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Road Width
    public function getRoadWidthAttribute()
    {
        return $this->parameters->where('id', config('global.road_width'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Price per m2
    public function getPriceM2Attribute()
    {
        return $this->parameters->where('id', config('global.price_m2'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ

    //HuyTBQ: add function get number Floor
    public function getNumberFloorAttribute()
    {
        return $this->parameters->where('id', config('global.number_floor'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ

    //HuyTBQ: add function get number room
    public function getNumberRoomAttribute()
    {
        return $this->parameters->where('id', config('global.number_room'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ

    // HuyTBQ: add function get Bathroom
    public function getBathroomAttribute()
    {
        return $this->parameters->where('id', config('global.bathroom'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Garage
    public function getGarageAttribute()
    {
        return $this->parameters->where('id', config('global.garage'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Pool
    public function getPoolAttribute()
    {
        return $this->parameters->where('id', config('global.pool'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Furniture
    public function getFurnitureAttribute()
    {
        return $this->parameters->where('id', config('global.furniture'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Construction Status
    public function getConstructionStatusAttribute()
    {
        return $this->parameters->where('id', config('global.construction_status'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    // HuyTBQ: add function get Rental Period
    public function getRentalPeriodAttribute()
    {
        return $this->parameters->where('id', config('global.rental_period'))->first()->pivot->value ?? null;
    }
    //End HuyTBQ
    //HuyTBQ: add function get count image
    public function getImagesCountAttribute()
    {
        return PropertyImages::where('propertys_id', $this->id)->count();
    }
    //End HuyTBQ

    //HuyTBQ: add function get address location
    public function getAddressLocationAttribute()
    {
        return optional($this->street)->street_name . ', ' . optional($this->ward)->name;
    }
    //End HuyTBQ
    //HuyTBQ: add function get title
    public function getTitleByAddressAttribute()
    {
        $address = $this->getAddressLocationAttribute();

        if ($this->propery_type == 0) {
            return "Bán " . $this->category->category . ', ' . $address . ', Tp Đà Lạt';
        } elseif ($this->propery_type == 1) {
            return "Cho thuê " . $this->category->category . ', ' . $address;
        } else {
            return $address;
        }
    }
    //End HuyTBQ

    //HuyTBQ: add function get formatted prices
    // public function getFormattedPricesAttribute()
    // {
    //     \Carbon\Carbon::setLocale('vi');
    //     $formatter = new \NumberFormatter('vi_VN', \NumberFormatter::CURRENCY);

    //     $price = $this->price;
    //     $ty = 1000000000;
    //     $trieu = 1000000;

    //     if ($price > $ty) {
    //         if ($price % $ty == 0) {
    //             $formattedPrice = number_format($price / $ty, 0) . ' tỷ';
    //         } else {
    //             $formattedPrice = number_format($price / $ty, 1) . ' tỷ';
    //         }
    //     } elseif ($price > 0) {
    //         $formattedPrice = number_format($price / $trieu, 0) . ' triệu';
    //     } else {
    //         $formattedPrice = 'Giá thỏa thuận';
    //     }

    //     return $formattedPrice;
    // }

    //HuyTBQ: add function get formatted prices
    public function getFormattedPricesAttribute()
    {
        \Carbon\Carbon::setLocale('vi');
        $formatter = new \NumberFormatter('vi_VN', \NumberFormatter::CURRENCY);

        $price = $this->price;
        $ty = 1000000000;
        $trieu = 1000000;
        $suffix = "";

        // Check property type and set suffix accordingly
        if ($this->propery_type == 1) {
            switch ($this->rentduration) {
                case "Monthly":
                    $suffix = ' / tháng';
                    break;
                case "Daily":
                    $suffix = ' / ngày';
                    break;
                case "Yearly":
                    $suffix = ' / năm';
                    break;
                case "Quarterly":
                    $suffix = ' / quý';
                    break;
                default:
                    // Do nothing
                    break;
            }
        }

        // Calculate formatted price
        if ($price > $ty) {
            $formattedPrice = number_format($price / $ty, ($price % $ty == 0) ? 0 : 1) . ' tỷ';
        } elseif ($price > 0) {
            $formattedPrice = number_format($price / $trieu, 0) . ' triệu';
        } else {
            $formattedPrice = 'Giá thỏa thuận';
        }

        // Append suffix if applicable
        if ($suffix !== "") {
            $formattedPrice .= $suffix;
        }

        return $formattedPrice;
    }

    //End HuyTBQ
    //HuyTBQ: add function get format price m2
    public function getFormattedPriceM2Attribute()
    {
        // Kiểm tra nếu diện tích bằng 0 hoặc không có diện tích
        if ($this->area == 0 || $this->area == null) {
            return 'Chưa xác định';
        }

        // Tính giá trị price_m2
        $priceM2 = $this->price / $this->area;

        // Định dạng giá trị price_m2
        $ty = 1000000000;
        $trieu = 1000000;
        if ($priceM2 > $ty) {
            if ($priceM2 % $ty == 0) {
                $formattedPriceM2 = number_format($priceM2 / $ty, 0) . ' tỷ/m²';
            } else {
                $formattedPriceM2 = number_format($priceM2 / $ty, 1) . ' tỷ/m²';
            }
        } elseif ($priceM2 > 0) {
            $formattedPriceM2 = number_format($priceM2 / $trieu, 1) . ' triệu/m²';
        } else {
            $formattedPriceM2 = 'Giá thỏa thuận';
        }

        return $formattedPriceM2;
    }

    //End HuyTBQ
    //HuyTBQ: add function get title
    public function getTypeAttribute()
    {
        if ($this->propery_type == 0) {
            return 'Bán';
        } elseif ($this->propery_type == 1) {
            return 'Cho thuê';
        } else {
            return null; // Hoặc bất kỳ giá trị mặc định nào bạn muốn nếu không có giá trị phù hợp
        }
    }
    //End HuyTBQ
    //HuyTBQ: add function get title
    public function getCountPropertiesByAgentAttribute()
    {
        $agentId = $this->added_by;
        return Property::where('added_by', $agentId)->get()->count();
    }
    //End HuyTBQ

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category', 'parameter_types', 'image');
    }
    public function customer()
    {
        return $this->hasMany(Customer::class, 'id', 'added_by', 'fcm_id', 'notification');
    }
    public function user()
    {
        return $this->hasMany(User::class, 'id', 'added_by', 'fcm_id', 'notification');
    }

    public function assignParameter()
    {
        return  $this->morphMany(AssignParameters::class, 'modal');
    }

    public function parameters()
    {
        return $this->belongsToMany(parameter::class, 'assign_parameters', 'modal_id', 'parameter_id')
                    ->withPivot('value')
                    ->orderBy('parameters.order');
    }
    public function assignfacilities()
    {
        return $this->hasMany(AssignedOutdoorFacilities::class);
    }

    public function favourite()
    {
        return $this->hasMany(Favourite::class);
    }
    public function interested_users()
    {
        return $this->hasMany(InterestedUser::class);
    }
    // public function assign_parameter()
    // {
    //     return $this->hasMany(AssignParameters::class);
    // }
    public function advertisement()
    {
        return $this->hasMany(Advertisement::class);
    }

    public function getGalleryAttribute()
    {
        $data = PropertyImages::select('id', 'image')->where('propertys_id', $this->id)->get();


        foreach ($data as $item) {
            if ($item['image'] != '') {
                $item['image'] = $item['image'];
                $item['image_url'] = ($item['image'] != '') ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_GALLERY_IMG_PATH') . $this->id . "/" . $item['image'] : '';
            }
        }
        return $data;
    }

    public function getLegalImagesAttribute()
    {
        $data = PropertyLegalImage::select('id', 'image')->where('propertys_id', $this->id)->get();


        foreach ($data as $item) {
            if ($item['image'] != '') {
                $item['image'] = $item['image'];
                $item['image_url'] = ($item['image'] != '') ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_GALLERY_IMG_PATH') . $this->id . "/" . $item['image'] : '';
            }
        }
        return $data;
    }

    public function getTitleImageAttribute($image)
    {
        return $image != '' ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_TITLE_IMG_PATH') . $image : '';
    }
    public function getThreeDImageAttribute($threeDimage)
    {
        return $threeDimage != '' ? url('') . config('global.IMG_PATH') . config('global.3D_IMG_PATH') . $threeDimage : '';
    }

    protected $casts = [
        'category_id' => 'integer',
    ];
}
