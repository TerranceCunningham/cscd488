<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\Asset;
use App\Models\Inventory;
use App\Models\User;
use App\Models\Blueteam;
use App\Models\Setting;
use View;
use Auth;
use Exception;


class BlueTeamController extends Controller {

    public function page($page, Request $request) {

        if($page == 'startturn') return $this->startTurn(); //Testing Purposes
        $blueteam = Team::find(Auth::user()->blueteam);
        if($blueteam != null){
            $team_id = Auth::user()->blueteam;
            $turn = Blueteam::all()->where('team_id','=',$team_id)->first()->turn_taken;
            if($turn == 1){
                $endTime = Setting::get('turn_end_time');
                return $this->home()->with(compact('turn', 'endTime'));
            }
        }
        switch ($page) {
            case 'home': return $this->home(); break;
            case 'planning': return view('blueteam.planning')->with('blueteam',$blueteam); break;
            case 'status': return view('blueteam.status')->with('blueteam',$blueteam); break;
            case 'store': return $this->store();
            case 'training': return view('blueteam.training')->with('blueteam',$blueteam); break;
            case 'create': return $this->create($request); break;
            case 'join': return $this->join($request); break;
            case 'buy': return $this->buy($request); break;
            case 'storeinventory': return $this->storeInventory(); break;
            case 'sell': return $this->sell($request); break;
            case 'endturn': return $this->endTurn(); break;
        }

    }
 
    public function startTurn(){ //Testing Purposes
        $teamID = Auth::user()->blueteam;
        $blueteam = Blueteam::all()->where('team_id','=',$teamID)->first();
        $blueteam->turn_taken = 0;
        $blueteam->update();
        $turn = 0;
        return $this->home()->with(compact('turn'));
    }

    public function endTurn(){
        $teamID = Auth::user()->blueteam;
        $blueteam = Blueteam::all()->where('team_id','=',$teamID)->first();
        $blueteam->turn_taken = 1;
        $blueteam->update();
        $turn = 1;
        $endTime = Setting::get('turn_end_time');
        return $this->home()->with(compact('turn', 'endTime'));
    }

    public function home(){
        $blueid = Auth::user()->blueteam;
        $blueteam = Team::find($blueid);
        if($blueid == "") return view('blueteam.home')->with(compact('blueteam'));
        $leader = User::all()->where('blueteam','=',$blueid)->where('leader','=',1)->first();
        $members = User::all()->where('blueteam','=',$blueid)->where('leader','=',0);
        $turn = 0;
        return  view('blueteam.home')->with(compact('blueteam','leader','members', 'turn'));
    }

    public function sell(request $request){
        //change this to proportion sell rate
        $sellRate = 1;
        $assetNames = $request->input('results');
        $assets = Asset::all()->where('blue', '=', 1)->where('buyable', '=', 1);
        if($assetNames == null){
            $blueteam = Team::find(Auth::user()->blueteam);
            $error = "no-asset-selected";
            return view('blueteam.store')->with(compact('assets','error', 'blueteam'));
        }
        $totalCost = 0;
        foreach($assetNames as $assetName){
            $asset = Asset::all()->where('name','=',$assetName)->first();
            if($asset == null){
                throw new Exception("invalid-asset-name");
            }
            $totalCost += ($asset->purchase_cost * $sellRate);
        }
        $blueteam = Team::find(Auth::user()->blueteam);
        if($blueteam == null){
            throw new Exception("invalid-team-selected");
        }
        foreach($assetNames as $asset){
            //add asset to inventory and charge team
            $assetId = substr(Asset::all()->where('name','=',$asset)->pluck('id'), 1, 1);
            $currAsset = Inventory::all()->where('team_id','=',Auth::user()->blueteam)->where('asset_id','=', $assetId)->first();
            if($currAsset == null){
                throw new Exception("do-not-own-asset");
            }else{
                $currAsset->quantity -= 1;
                if($currAsset->quantity == 0){
                    Inventory::destroy(substr($currAsset->pluck('id'),1,1));
                }else{
                    $currAsset->update();
                }
                $blueteam->balance += $totalCost;
                $blueteam->update();
                return view('blueteam.store')->with(compact('blueteam', 'assets'));
            }
        }
    }//end sell

    public function storeInventory(){
        $blueteam = Team::find(Auth::user()->blueteam);
        $inventory = Inventory::all()->where('team_id','=', Auth::user()->blueteam);
        $assets = Asset::all()->where('blue', '=', 1)->where('buyable', '=', 1);
        return view('blueteam.store')->with(compact('blueteam', 'assets', 'inventory'));
    }

    public function buy(request $request){
        $assetNames = $request->input('results');
        $assets = Asset::all()->where('blue', '=', 1)->where('buyable', '=', 1);
        if($assetNames == null){
            $blueteam = Team::find(Auth::user()->blueteam);
            $error = "no-asset-selected";
            return view('blueteam.store')->with(compact('assets','error', 'blueteam'));
        }
        $totalCost = 0;
        foreach($assetNames as $assetName){
            $asset = Asset::all()->where('name','=',$assetName)->first();
            if($asset == null){
                throw new Exception("invalid-asset-name");
            }
            $totalCost += $asset->purchase_cost;
        }
        $blueteam = Team::find(Auth::user()->blueteam);
        if($blueteam == null){
            throw new Exception("invalid-team-selected");
        }
        //$blueteam->balance = 1000; //DELETE THIS IS FOR TESTING PURPOSES
        if($blueteam->balance < $totalCost){
            $assets = Asset::all()->where('blue', '=', 1)->where('buyable', '=', 1);
            $error = "not-enough-money";
            return view('blueteam.store')->with(compact('assets','error','blueteam'));
        }
        foreach($assetNames as $asset){
            //add asset to inventory and charge team
            $assetId = substr(Asset::all()->where('name','=',$asset)->pluck('id'), 1, 1);
            $currAsset = Inventory::all()->where('team_id','=',Auth::user()->blueteam)->where('asset_id','=', $assetId)->first();
            if($currAsset == null){
                $currAsset = new Inventory();
                $currAsset->team_id = Auth::user()->blueteam;
                $currAsset->asset_id = $assetId;
                $currAsset->quantity = 1;
                $currAsset->save();
            }else{
                $currAsset->quantity += 1;
                $currAsset->update();
            }
        }
        $blueteam->balance -= $totalCost;
        $blueteam->update();
        return view('blueteam.store')->with(compact('blueteam', 'assets'));
    }//end buy

    public function store(){
        $blueteam = Team::find(Auth::user()->blueteam);
        $assets = Asset::all()->where('blue', '=', 1)->where('buyable', '=', 1);
        return view('blueteam.store')->with(compact('blueteam', 'assets'));
    }

    public function join(request $request){
        if($request->result == ""){
            $blueteams = Team::all()->where('blue', '=', 1);
            return view('blueteam.join')->with('blueteams', $blueteams);
        }
        $user = Auth::user();
        $blueteam = Team::all()->where('name', '=', $request->result);
        if($blueteam->isEmpty()) throw new Exception("TeamDoesNotExist");
        $user->blueteam = substr($blueteam->pluck('id'), 1, 1);
        $user->update();
        return $this->home();
    }

    public function create(request $request){
        if($request->name == "") return view('blueteam.create'); 
        $this->validate($request, [
            'name' => ['required', 'unique:teams', 'string', 'max:255'],
        ]);
        $team = new Team();
        $team->name = $request->name;
        $team->balance = 0;
        $team->blue = 1;
        $team->reputation = 0;
        $team->save();
        $blueteam = new Blueteam();
        $teamID = substr(Team::all()->where('name', '=', $request->name)->pluck('id'), 1, 1);
        $blueteam->team_id = $teamID;
        $blueteam->save();
        $user = Auth::user();
        $user->blueteam = $teamID;
        $user->leader = 1;
        $user->update();
        return $this->home();
    }

    public function delete(request $request){
        $team = Team::all()->where('name', '=', $request->name);
        if($team->isEmpty()) {
            throw new Exception("TeamDoesNotExist");
        }
        $id = substr($team->pluck('id'), 1, 1);
        Team::destroy($id);
        return view('home');
    }

}
