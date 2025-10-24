<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class Chats extends Model
{
    use HasFactory;
     public function sender()
    {
        return $this->belongsTo(Customer::class, 'sender_id');
    }
    public function receiver()
    {
        return $this->belongsTo(Customer::class, 'receiver_id');
    }
       public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
      public function getFileAttribute($file)
    {
        return $file != "" ? url('') . config('global.IMG_PATH') . config('global.CHAT_FILE') . $file : '';
    }
    public function getAudioAttribute($value)
    {
        return $value != "" ? url('') . config('global.IMG_PATH') . config('global.CHAT_AUDIO') . $value : '';
    }

  
}
