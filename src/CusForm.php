<?php


namespace CusForm;


use CusForm\Model\FormApplyContentModel;
use CusForm\Model\FormItemModel;
use Qscmf\Builder\FormBuilder;
use Qscmf\Lib\DBCont;

class CusForm
{
    private static $_instance;

    private function __construct()
    {
    }

    public static function getInstance(){
        if (!self::$_instance){
            self::$_instance=new CusForm();
        }
        return self::$_instance;
    }

    /**
     * 前台获取form_item内容
     * @param int|string $form_id 表单id
     * @param int|string $apply_id 表单提交id
     * @return array
     */
    public function getItemData($form_id,$apply_id=''){
        $res=[];
        $formItemModel=new FormItemModel();
        $formApplyContentModel=new FormApplyContentModel();
        $formItems=$formItemModel->where(['form_id'=>$form_id,'deleted'=>DBCont::NO_BOOL_STATUS])->order('sort asc')->select();
        foreach ($formItems as $formItem) {
            $re=$formItem;
            unset($re['id']);
            unset($re['create_date']);
            unset($re['sort']);
            unset($re['form_id']);
            $re['name']='cus_form_'.$formItem['id'];
            if ($apply_id){
                $map=[
                    'form_apply_id'=>$apply_id,
                    'form_item_id'=>$formItem['id']
                ];
                $re['value']=$formApplyContentModel->where($map)->getField('content');
            }
            $res[]=$re;
        }
        return $res;
    }

    /**
     * 后台显示用户所填写的内容
     * @param $builder FormBuilder FormBuilder
     * @param int|string $form_id 表单id
     * @param int|string $apply_id 表单提交id
     * @param null $form_data 表单数据
     * @return FormBuilder
     */
    public function generateFormItem($builder, $form_id, $apply_id, &$form_data = null){
        $items=D('FormItem')->where(['form_id'=>$form_id,'deleted'=>DBCont::NO_BOOL_STATUS])->order('sort asc')->select();
        $info = [];
        foreach ($items as $item) {
            $ent =D('FormApplyContent')->where(['form_apply_id'=>$apply_id,'form_item_id'=>$item['id']])->find();
            $key = 'cusform_' . $ent['id'];
            if($ent){
                $info[$key] = $ent['content'];
            }
            $this->addFormItem($builder, $item, $key, $ent['content']);
        }
        if($form_data){
            $form_data = array_merge($form_data, $info);
        }
        return $builder;
    }

    private function addFormItem($builder, $item, $content_id, $content){
        switch($item['type']){
            case FormItemModel::PICTURE:
            case FormItemModel::PICTURES:
                $builder->addFormItem($content_id, $item['type'], $item['title'], '', [], '', '', [], ['read_only' => true]);
                break;
            default:
                $builder->addFormItem($content_id, 'self', $item['title'], '', $this->_genStaticHtml($item, $content));
                break;
        }
    }

    private function _genStaticHtml($item,$content){
        $html='<p style="padding-top: 7px;">';
        switch ($item['type']){
            case FormItemModel::CITY:
                $html.=getFullAreaByID($content);
                break;
            case FormItemModel::FILE:
                $title=D('FilePic')->where(['id'=>$content])->getField('title');
                $html.='<a download href="'.showFileUrl($content).'">'.$title.'</a>';
                break;
            case FormItemModel::RADIO_TEXT:
            case FormItemModel::CHECKBOX_TEXT:
                $arr = json_decode(htmlspecialchars_decode($content), true);
                if(isset($arr['title'])){
                    $arr = [ $arr ];
                }
                collect($arr)->each(function($item) use (&$html){
                    $html .= "<p>{$item['title']}";
                    if(isset($item['text'])){
                        $html .= " | {$item['text']}";
                    }
                    $html .= "</p>";
                });
                break;
            case FormItemModel::TEXTAREA:
                $html .= '<textarea class="form-control textarea" rows="5" name="message" readonly>'.$content.'</textarea>';
                break;
            default:
                $html.=$content;
                break;
        }
        $html.='</p>';
        return $html;
    }

    public function saveContent($form_id,$data){
        $model=new FormApplyContentModel();
        $r = $model->saveAll($form_id,$data);
        if ($r===false){
            E($model->getError());
        }
        return $r;
    }
}