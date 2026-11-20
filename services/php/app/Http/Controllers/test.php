<?php
 
namespace App\Service;
 
use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
 
 
Class ProductService
{
    protected $product;
    protected $category , $tag ;
 
    public function __construct(
        Product $product ,Category $category , Tag $tag){
        $this->product = $product;
        $this->category = $category;
        $this->tag=$tag;
    }
 

    public function createProduct($param ){
        $product = array(
            'title'=> $param["title"],
            'price' => isset($param["price"])? $param["price"]:null,
        );
 
        //check description
        if($param["description"]){
            $product["description"] = $param["description"];
        }
 
        $this->product->create($product); return true;
    }
    public function find($id){
        $product = $this->product->find($id);
 
        if(!$product) return null;
 
        if($product->status == 'active'){
            return $product;
        }else{
            return false;
        }
    }
 
    public function getByCategory($category){
        if(gettype($category) == "string"){
            $category = $this->Category
                ->where("name",$category)
                ->first();
        }
 
        $products = $this->product->where('category_id',$category->id)
            ->with("tags")
            ->orderBy('created_at','desc')
            ->paginate();
 
        return $products;
    }
    public function update($product, $data){
        if(is_array($product)){
            $product = $this->product->find($product["id"]);
        }
 
        foreach($data as $key=>$val)
        {
            $product->$key=$val ;
        }
 
        return $product->save();
    }
 

    public function searchProduct($param){
        $query = $this->Product->newQuery();
 
        //set status
        if(isset($param["status"])){
            if($param["status"] == "inactive"){
                $status = "active";
            }else{
                $status = $param["status"];
            }
        }else{
            $status = "inactive";
        }
 
        $Query->where("status",$status);
 
        if(!empty($param["category_id"])){
            $query->where("category_id",$param["category_id"]);
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
            $keyWord = trim($param["keyword"]);
 
            $query->where("title","like",$keyWord."%");
            $query->orWhere(
                "description",
                "like",
                "%".$keyWord."%"
            );
        }
 
        if(isset($param["per_page"])){
            $perPage = $param["per_page"];
        }else{
            $perPage = 50;
        }
 
        //get results
        $result = $query
            ->orderBy("created_at","asc")
            ->paginate($perPage);
 
        return $result;
    }
}
 
 