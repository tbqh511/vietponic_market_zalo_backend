<?php

namespace App\Http\Controllers;

use App\Models\Chats;
use App\Models\Customer;
use App\Models\PropertysInquiry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Type\Time;
use Tymon\JWTAuth\Claims\Custom;
use App\Models\Property;

class ChatController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->toArray());
        $chat = new Chats();
        $chat->sender_id = $request->sender_by;
        $chat->receiver_id = $request->receiver_id;
        $chat->message = $request->message ? $request->message : '';
        $chat->property_id = $request->property_id;





        $audio_data = $request->aud;
        if ($audio_data) {

            // Decode the data URL and extract the raw audio data
            $audio_data = str_replace('data:audio/mp3; codecs=opus;base64,', '', $audio_data);
            $audio_data = base64_decode($audio_data);

            // Save the audio data to a file
            $filename = uniqid() . '.mp3';
            $audiodestinationPath = public_path('images/chat_audio/') . $filename;
            if (!is_dir(dirname($audiodestinationPath))) {
                mkdir(dirname($audiodestinationPath), 0777, true);
            }

            file_put_contents($audiodestinationPath, $audio_data);


            $chat->audio = $filename;
        }
        $destinationPath = public_path('images') . config('global.CHAT_FILE');

        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }
        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment');
            $imageName = microtime(true) . "." . $attachment->getClientOriginalExtension();
            $attachment->move($destinationPath, $imageName);
            $chat->file = $imageName;
        } else {
            $chat->file = '';
        }

        $chat->save();
        $chat->save();
        $fcm_id = [];
        $customer = Customer::select('id', 'fcm_id', 'name')->with(['usertokens' => function ($q) {
            $q->select('fcm_id', 'id', 'customer_id');
        }])->find($request->receiver_id);

        if ($customer && !empty($customer->usertokens)) {
            foreach ($customer->usertokens as $usertokens) {

                array_push($fcm_id, $usertokens->fcm_id);
            }

            $username = $customer->name;
        } else {
            $fcm_id = [];
        }


        $Property = Property::find($request->property_id);

        $fcmMsg = array(
            'title' => 'Message',
            'message' => $request->message,
            'type' => 'chat',
            'body' => $request->message,
            'sender_id' => $request->sender_by,
            'receiver_id' => $request->receiver_id,
            'username' => $username,
            'file' => $chat->file != '' ? $chat->file : '',
            'audio' => $chat->audio,
            'date' => $chat->created_at,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'sound' => 'default',
            'property_id' => $Property->id,
            'property_title_image' => $Property->title_image,
            'title' => $Property->title,
        );
        // echo($customer->fcm_id);
        send_push_notification($fcm_id, $fcmMsg);

        $response['error'] = false;
        return response()->json($response);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function getChats()
    {
        // dd($_GET);
        $current_user = Auth::user()->id;

        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }

        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }

        if (isset($_GET['sort'])) {
            $sort = $_GET['sort'];
        }

        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        DB::enableQueryLog();
        // dd($current_user);
        $user_list =

            Chats::with(['sender', 'receiver'])->with('property')
            ->select('id', 'sender_id', 'receiver_id', 'property_id', 'message', 'created_at')
            ->where('sender_id', 0)
            ->orWhere('receiver_id', 0)
            ->orderBy('id', 'desc')
            ->groupBy('property_id',)->get();
        $firebase_settings = array();

        $firebase_settings['apiKey'] = system_setting('apiKey');
        $firebase_settings['authDomain'] = system_setting('authDomain');
        $firebase_settings['projectId'] = system_setting('projectId');
        $firebase_settings['storageBucket'] = system_setting('storageBucket');
        $firebase_settings['messagingSenderId'] = system_setting('messagingSenderId');
        $firebase_settings['appId'] = system_setting('appId');
        $firebase_settings['measurementId'] = system_setting('measurementId');
        return view('chat.index', ['user_list' => $user_list, 'firebase_settings' => $firebase_settings]);
    }
    public function getAllMessage(Request $request)
    {

        $property_id = $request->propert_id;
        $offset = $request->offset ? $request->offset : 0;
        $limit = $request->limit ? $request->limit : 10;


        $chat = Chats::with('sender')->with('receiver')->with('property')->select('id', 'sender_id', 'receiver_id', 'message', 'audio', 'property_id', 'file', 'created_at')->where('property_id', $request->property_id)
            ->where(function ($query) use ($request) {
                $query->where('sender_id', $_GET['client_id'])
                    ->orWhere('receiver_id', $_GET['client_id']);
            })->orderBy('id', 'DESC')->get();


        $rows = array();
        $tempRow = array();
        $count = 1;
        foreach ($chat as $row) {
            // dd($row->toArray());
            if ($row->sender_id  == 0 || $row->receiver_id == 0) {
                $tempRow['message'] = $row->message;

                $current = Carbon::parse(date('Y/m/d h:i:s'), 'Asia/Kolkata');
                $test = Carbon::parse(($row->created_at), 'Asia/Kolkata');

                $tempRow['time_ago'] = $row->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true);

                $tempRow['attachment'] = $row->file;
                $tempRow['audio'] = !empty($row->audio) ? $row->audio : '';


                if ($row->receiver_id == 0) {
                    $customer = Customer::find($row->sender_id);
                    if ($customer) {
                        $name = $customer->name;
                        $profile = $customer->profile;
                    } else {
                        $name = "Admin";
                        $profile = '';
                    }
                    $tempRow['sendeprofile'] = $profile;

                    $tempRow['sender_type'] = 1;

                    $tempRow['sendername'] = $name;
                }
                if ($row->sender_id  == 0) {



                    // $user = User::find($row->sender_id);

                    $customer = Customer::find($row->receiver_id);
                    if ($row->property->added_by != 0) {

                        $name = $customer->name;
                        $profile = $customer->profile;
                    }
                    if ($row->property->added_by == 0) {

                        $name = "Admin";
                        $profile = '';
                    }
                    // $tempRow['attachment'] = $row->file;

                    $tempRow['ssendeprofile'] = $profile;
                    $tempRow['ssendername'] = $name;

                    $tempRow['sender_type'] = 0;
                }

                $rows[] = $tempRow;
                $count++;
            }
        }

        $bulkData['rows'] = $rows;
        return response()->json($rows);
    }
}
