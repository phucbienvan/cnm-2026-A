<?php
 
namespace App\Service;
 
use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
 
 
Class productService
{
    protected $Product;
    protected $Category , $Tag ;
 
    public function __construct(
        Product $Product ,Category $Category , Tag $Tag){
        $this->Product = $Product;
        $this->Category = $Category;
        $this->Tag=$Tag;
    }
 

    public function createproduct( $param ){
        $product = array(
            'title'=> $param["title"],
            'price' => isset($param["price"])? $param["price"]:null,
        );
 
        //check description
        if($param["description"]){
        $product["description"] = $param["description"];
        }
 
        $this->Product->create($product); return true;
    }
    public function FIND($id){
        $Product = $this->Product->find($id);
 
        if(!$Product) return null;
 
        if($Product->status == 'active'){
            return $Product;
        }else{
            return FALSE;
        }
    }
 
    public function getByCategory($category){
        if(gettype($category) == "string"){
            $category = $this->Category
                ->where("name",$category)
                ->first();
        }
 
        $Products = $this->Product->where('category_id',$category->id)
            ->with("tags")
            ->orderBy( 'created_at','desc')
            ->paginate();
 
        return $Products ;
    }
    public function update($product, $data){
        if(is_array($product)){
            $product = $this->Product->find($product["id"]);
        }
 
        foreach($data as $Key=>$Val)
        {
            $product->$Key=$Val ;
        }
 
        return $product->save();
    }
 

    public function search_product($param){
        $Query = $this->Product->newQuery();
 
        //set status
        if(isset($param["status"])){
            if($param["status"] == "inactive"){
                $Status = "active";
            }else{
                $Status = $param["status"];
            }
        }else{
            $Status = "inactive";
        }
 
        $Query->where("status",$Status);
 
        if(!empty($param["category_id"])){
            $Query->where("category_id",$param["category_id"]);
        }
 
        //filter price
        if(!empty($param["min_price"])){
            if(!empty($param["max_price"])){
                $Query->whereBetween("price", [
                    $param["min_price"],
                    $param["max_price"]
                ]);
            }else{
                $Query->where("price",">",$param["min_price"]);
            }
        }
 
        if($param["keyword"]){
            $KeyWord = trim($param["keyword"]);
 
            $Query->where("title","like",$KeyWord."%");
            $Query->orWhere(
                "description",
                "like",
                "%".$KeyWord."%"
            );
        }
 
        if(isset($param["per_page"])){
            $PerPage = $param["per_page"];
        }else{
            $PerPage = 50;
        }
 
        //get results
        $Result = $Query
            ->orderBy("created_at","asc")
            ->paginate($PerPage);
 
        return $Result;
    }
}
 
 