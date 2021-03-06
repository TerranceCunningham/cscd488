<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\Asset;
use App\Models\Inventory;
use App\Models\Attack;
use Auth;
use App\Exceptions\AssetNotFoundException;
use App\Exceptions\TeamNotFoundException;
use App\Models\AttackLog;
use Error;

class RedTeamController extends Controller {

    public function page($page, Request $request) {
        try{
            $redteam = Auth::user()->getRedTeam();
        }catch(TeamNotFoundException $e){
            $redteam = null;
        }
        if($redteam == null){
            switch ($page) {
                case 'home': return $this->home(); break;
                case 'create': return $this->create($request); break;
                default: return $this->home(); break;
            }
        }else{
            switch ($page) {
                case 'home': return $this->home(); break;
                case 'attacks': return $this->attacks(); break;
                case 'learn': return view('redteam.learn')->with('redteam',$redteam); break;
                case 'store': return $this->store();break;
                case 'status': return view('redteam.status')->with('redteam',$redteam); break;
                case 'buy': return $this->buy($request); break;
                case 'storeinventory': return $this->storeInventory(); break;
                case 'sell': return $this->sell($request); break;
                case 'startattack': return $this->startAttack(); break;
                case 'chooseattack': return $this->chooseAttack($request); break;
                case 'performattack': return $this->performAttack($request); break;
                case 'attackhandler': return $this->attackHandler($request); break;
                case 'settings': return $this->settings($request); break;
                case 'changename': return $this->changeName($request); break;
                case 'leaveteam': return $this->leaveTeam($request); break;
                case 'minigamecomplete': return $this->minigameComplete($request); break;
                default: return $this->home(); break;
            }
        }
    }

    public function leaveTeam(request $request){
        if($request->result == "stay"){
            return $this->settings($request);
        }
        else if($request->result != "leave"){
            $error = "invalid-choice";
            return $this->settings($request)->with(compact('error'));
        }
        Auth::user()->leaveRedTeam();
        return $this->home();
    }

    public function changeName(request $request){
        try{
            Team::get($request->name);
        }catch(TeamNotFoundException $e){
            $team = Auth::user()->getRedTeam();
            $team->setName($request->name);
        }
        $error = "name-taken";
        return $this->settings($request)->with(compact('error'));
    }

    public function settings($request){
        $changeName = false;
        $leaveTeam = false;
        if($request->changeNameBtn == 1){
            $changeName = true;
        }
        if($request->leaveTeamBtn == 1){
            $leaveTeam = true;
        }
        $redteam = Auth::user()->getRedTeam();
        return view('redteam/settings')->with(compact('redteam','changeName','leaveTeam'));
    }

    public function home(){
        try{
            $redteam = Auth::user()->getRedTeam();
        }catch(TeamNotFoundException $e){
            $redteam = null;
        }
        return view('redteam.home')->with(compact('redteam'));
    }

    public function attacks(){
        $possibleAttacks = Attack::all();
        $redteam = Auth::user()->getRedTeam();
        return view('redteam.attacks')->with(compact('redteam','possibleAttacks')); 
    }

    public function minigameComplete(request $request){
        $attackLog = AttackLog::find($request->attackLogID);
        $attMsg = "Success: ";
        if($request->result == 1){
            $attackLog->success = true;
            $attMsg .= "true";
        }else{
            $attackLog->success = false;
            $attMsg .= "false";
        }
        $attackLog->update();
        try {
            $attack = Attack::find($attackLog->attack_id);
            $attack->onAttackComplete($attackLog);
        }
        catch (TeamNotFoundException $e){
            $attMsg = "Error while executing attack. Attack was not completed.";
        }
        return $this->home()->with(compact('attMsg'));
    }

    public function minigameStart($attackLog){
        if(!$attackLog->possible){
            $attMsg = "Success: impossible";
            return $this->home()->with(compact('attMsg'));
        }
        //possibly find the minigame for that attack, then return different view or minigame
        $redteam = Team::find($attackLog->redteam_id);
        $blueteam = Team::find($attackLog->blueteam_id);
        $attack = Attack::find($attackLog->attack_id);
        if($redteam == null || $blueteam == null){
            throw new TeamNotFoundException();
        }
        if($attack == null){
            throw new AssetNotFoundException();
        }
        return view('redteam.minigame')->with(compact('attackLog','redteam','blueteam','attack'));
    }

    public function performAttack(request $request){
        if($request->result == ""){
            $error = "No-Attack-Selected";
            return $this->chooseAttack($request)->with(compact('error'));
        }
        $redteam = Team::find(Auth::user()->redteam);
        $blueteam = Team::all()->where('name','=',$request->blueteam)->first();
        $attack = Attack::all()->where('name', '=', $request->result)->first();
        if($attack == null){ throw new AssetNotFoundException();}
        elseif($blueteam == null || $redteam == null){ throw new TeamNotFoundException();}
        $attackLog = AttackLog::factory()->make([
            'attack_id' => $attack->id,
            'difficulty' => $attack->difficulty,
            'detection_chance' => $attack->detection_chance,
        ]);
        $class = "\\App\\Models\\Attacks\\" . $attack->name . "Attack";
        try{
            $attackHandler = new $class();
            $attackLog = $attackHandler->onPreAttack($attackLog);
        }catch(Error $e){
            throw new AssetNotFoundException();
        }
        $attackLog = $redteam->onPreAttack($attackLog);
        $attackLog = $blueteam->onPreAttack($attackLog);
        //call onPreAttack for the attack itself?
        $attackLog->save();
        return $this->minigameStart($attackLog);
    }

    public function chooseAttack(request $request){
        if($request->result == ""){
            $error = "No-Team-Selected";
            return $this->startAttack()->with(compact('error'));
        }
        $user = Auth::user();
        $redteam = Team::find(Auth::user()->redteam);
        $blueteam = Team::all()->where('name', '=', $request->result);
        if($blueteam->isEmpty()){ throw new TeamNotFoundException();}
        $blueteam = $blueteam->first();
        $targetAssets = Inventory::all()->where('team_id','=', $blueteam);
        $possibleAttacks = Attack::all();
        return view('redteam.chooseAttack')->with(compact('redteam','blueteam','possibleAttacks'));
    }

    public function startAttack(){
        try{
            $targets = Team::getBlueTeams();
        }catch(TeamNotFoundException $e){
            $targets = [];
        }
        $redteam = Auth::user()->getRedTeam();
        return view('redteam.startAttack')->with(compact('targets','redteam'));
    }

    public function sell(request $request){
        //change this to proportion sell rate
        $sellRate = 1;
        $assetNames = $request->input('results');
        if($assetNames == null){
            $error = "no-asset-selected";
            return $this->store()->with(compact('error'));
        }
        $redteam = Auth::user()->getRedTeam();
        foreach($assetNames as $assetName){
            $asset = Asset::get($assetName);
            $success = $redteam->sellAsset($asset);
            if (!$success) {
                $error = "not-enough-owned-".$assetName;
                return $this->store()->with(compact('error'));
            }
        }
        return $this->store();
    }//end sell

    public function storeInventory(){
        $redteam = Auth::user()->getRedTeam();
        $inventory = $redteam->inventories();
        return $this->store()->with(compact('inventory'));
    }

    public function buy(request $request){
        $assetNames = $request->input('results');
        if($assetNames == null){
            $error = "no-asset-selected";
            return $this->store()->with(compact('error'));
        }
        $redteam = Auth::user()->getRedTeam();
        $totalCost = 0;
        //check total price
        foreach($assetNames as $assetName){
            $asset = Asset::get($assetName);
            $totalCost += $asset->purchase_cost;
        }
        if($redteam->balance < $totalCost){
            $error = "not-enough-money";
            return $this->store()->with(compact('error'));
        }
        //buy if you have enough
        foreach($assetNames as $assetName){
            $redteam = Auth::user()->getRedTeam();
            $asset = Asset::get($assetName);
            $redteam->buyAsset($asset);
        }
        return $this->store();
    }

    public function store(){
        $redteam = Auth::user()->getRedTeam();
        try{
            $assets = Asset::getBuyableRed();
        }catch(AssetNotFoundException $e){
            $assets = null;
        }
        return view('redteam.store')->with(compact('redteam', 'assets'));
    }

    public function create(request $request){
        if($request->name == ""){ return view('redteam.create');} 
        $request->validate([
            'name' => ['required', 'unique:teams', 'string', 'max:255'],
        ]);
        Auth::user()->createRedTeam($request->name);
        return $this->home();
    }

    public function delete(request $request){
        $team = Team::get($request->name);
        Auth::user()->deleteTeam($team);
        return view('home');
    }
}
