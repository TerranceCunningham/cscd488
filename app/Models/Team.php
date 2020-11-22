<?php

namespace App\Models;

use App\Interfaces\AttackHandler;
use Error;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\AssetNotFoundException;

class Team extends Model implements AttackHandler
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'balance',
        'reputation',
        'blue',
    ];

    public function onPreAttack($attackLog) {
        if ($this->blue == 1){
            $attackLog->blueteam_id = $this->id;
        }
        else if ($this->blue == 0){
            $attackLog->redteam_id = $this->id;
        }
        //check all assets and call onPreAttack() if possible
        $inventories = Inventory::all()->where('team_id','=', $this->id);
        foreach($inventories as $inventory){
            $asset = Asset::find($inventory->asset_id);
            $class = "\\App\\Models\\Assets\\" . $asset->name . "Asset";
            try {
                $attackHandler = new $class();
                $attackLog = $attackHandler->onPreAttack($attackLog);
            }
            catch (Error $e) {
                //Caused by specific asset model class not existing. So onPreAttack() cannot be called
                throw new AssetNotFoundException();
            }
        }
        return $attackLog;
    }

    public function leader() {
        return User::all()->where('blueteam','=',$this->id)->where('leader','=',1)->first();
    }

    public function members() {
        return User::all()->where('blueteam','=',$this->id)->where('leader','=',0);
    }

    public function assets() {
        return $this->hasManyThrough('App\Models\Asset', 'App\Models\Inventory');
    }

    public function inventories() {
        return $this->hasMany('App\Models\Inventory');
    }

    public function setName($newName) {
        $this->name = $newName;
        return $this->update();
    }
}
