<?php
// 本类由系统自动生成，仅供测试用途
namespace Home\Controller;
use Think\Controller;
class IndexController extends Controller {
    //登陆界面
    public function index(){
        // $this->produce_paper();
         $this->assign("action", U('Index/login'));
         $subjects = M('subject')->select();
         $this->assign('subjects',$subjects);
         $this->display();
    }

    //后面可以改为支持批量用户生成
    private function produce_paper($uid = 1, $subject_id = 1){
             $subject_id = 1;
             $qlist = M('Question')->where(['subject_id'=>$subject_id])->select();
             $optionlist = M('QuestionOption')->where(['subject_id'=>$subject_id])->select();
             if(!empty($optionlist)){
               foreach ($optionlist as $k => $v) {
                 $qolist[$v['question_id']][] = $v;
               }          
             }
             $sq_add  = [];
             $sqo_add = [];
             if(!empty($qlist)){
                 shuffle($qlist);
                 foreach ($qlist as $k => $v) {
                     $sq_add[] = [
                      'uid'=>$uid,
                      'question_id'=>$v['id'],
                      'subject_id' =>$v['subject_id'],
                      'desc'=>$v['desc']
                    ];
                    if(!empty($qolist[$v['id']])){
                        shuffle($qolist[$v['id']]); 
                        foreach ($qolist[$v['id']] as $v) {
                             $sqo_add[] = [
                              'uid'=>$uid,
                              'question_id'=>$v['question_id'],
                              'subject_id' =>$v['subject_id'],
                              'option_id'  =>$v['id'],
                              'desc'=>$v['desc']
                            ];                            
                        }
                    }
                 }
             }
             if(!empty($sq_add)){
                 M('StuQuestion')->addAll($sq_add);
             }
             if(!empty($sqo_add)){
                  M('StuQuestionOption')->addAll($sqo_add);
             }
    }

    public function exam(){
        $question_num = I('question_num',1,'intval');
        $answer       = I('answer',0,'intval');
        $questions_list =  M('StuQuestion')->where(['subject_id'=>1,'uid'=>1])->select();
        $total_num = !empty($questions_list) ? count($questions_list) : 0;
        //交卷
        if($question_num>$total_num){
            //更新学生科目考试状态
            echo '交卷成功!';
            exit;
        }
        foreach ($questions_list as $key => $value) {
            if(!empty($value['answer'])){
                $questions_list[$key]['has_done'] = 1;
            }else{
                $questions_list[$key]['has_done'] = 0;
            }
            $questions_list[$key]['key'] = $key+1;
            if(!isset($question) && $key == $question_num-1){
                $question = $value;
            }
        }
        if(!empty($question)){
            $pre = ['A','B','C','D'];
            $option_list =  M('StuQuestionOption')->where(['question_id'=>$question['question_id'],'uid'=>1,'subject_id'=>1])->select();
            if(!empty($option_list)){
                foreach ($option_list as $key => $value) {
                   $option_list[$key]['desc'] = $pre[$key].':'.$value['desc'];
                }
            }            
        }
        $is_first = $question_num == 1? 1: 0;
        $is_last  = $question_num == $total_num? 1: 0;
        $next_question_num = $question_num+1;
        $previous_question_num = $question_num-1;
        $this->assign('questions_list',$questions_list);     
        $this->assign('question_info',$question);     
        $this->assign('option',$option_list);     
        $this->assign('previous_question_num',$previous_question_num);     
        $this->assign('next_question_num',$next_question_num);     
        $this->assign('over_question_num',++$total_num);     
        $this->assign('is_first',$is_first);     
        $this->assign('is_last',$is_last);     
        $this->display();
    }

    //异步
    public function save_answer(){
        $question_id = I('question_id',0,'intval');
        $option_id = I('option_id',0,'intval');
        if(!empty($option_id) && !empty($question_id)){
         $res =  M('StuQuestion')->where(['question_id'=>$question_id,'uid'=>1,'subject_id'=>1])->save(['answer'=>$option_id]);
        }
        $result = ['data'=>[],'msg'=>'','code'=>0];
        exit(json_encode($result));
    }

    public function finish(){
        echo '交卷成功';
    }

    //登录
    public function login()
    {
        $unumber = I("post.unumber");
        $pwd = I("post.pwd");

        if(empty($unumber)){
            $this->error("学号不能为空！");
        }
        if(strlen($unumber)!=12){
            $this->error('请填写正确的学号！');
        }
        if (empty($pwd)) {
            $this->error("密码不能为空！");
        }
        //验证密码
        $user = $this->getUserInfo($unumber, $pwd);
        $subject_id = (int)I('post.subject');
        $subject_name = M('subject')->where(['id'=>$subject_id])->getField('subject_name');
        if(!empty($user)){
            $user['to_exam_subject'] = $subject_name;
        }
        $this->assign('user',$user);
        $this->display('comfir_msg');
        exit;
        if (empty($user)) {
            $this->error("密码错误！");
        }else{
            $subject_id = (int)I('post.subject');
            $time = time();
            $check_date = M('test_questions')->where("id=$subject_id AND $time<end_exam_date AND status!=2")->find();

            if(empty($check_date)){
                $this->error('现不是该科目的考试时间！');
            }

            if(strpos($user['exam_subject'],(string)$subject_id)===false){
                $this->error('请正确选择需考试的科目!');
            }
            $check_subject = M('exam_status')->where("uid={$user['uid']} AND subject_id=$subject_id AND status!=2")->find();
            if(empty($check_subject)){
                $this->error('请选择尚未考试的科目！');
            }

            unset($user['pwd']);
            $user['select_subject'] = $subject_id;
            $_SESSION['user'] = $user;
            $this->redirect('exam');
        }

    }

     /**
     * 获取用户信息
     * @param type $identifier 用户名或者用户ID
     * @return boolean|array
     */
    public function getUserInfo($unumber, $password = NULL) {

        $map = array();
        $map['unumber'] = $unumber;

        $userInfo = M('User')->where($map)->find();
        if (empty($userInfo)) {
            return false;
        }
        //密码验证
        if (!empty($password) && $this->hashPassword($password) != $userInfo['pwd']) {
            return false;
        }
        return $userInfo;
    }

    /**
     * 对明文密码，进行加密，返回加密后的密文密码
     * @param string $password 明文密码
     * @param string $verify 认证码
     * @return string 密文密码
     */
    public function hashPassword($password, $verify = "") {
        //return md5($password . md5($verify));
        return md5($password);
    }


    //退出
    public function logout()
    {
        unset($_SESSION['user']);
        $this->redirect('index');
    }






}