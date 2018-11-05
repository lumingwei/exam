<?php
// 本类由系统自动生成，仅供测试用途
namespace Home\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function _initialize()
    {
        // 发送header, 修复 IE 浏览器在 iframe 下限制写入 cookie 的问题
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        //登錄狀問題
        if(!empty($_SESSION['user'])){
            $this->uid = $_SESSION['user']['uid'];
            $this->subject_id = $_SESSION['user']['subject_id'];         
        }
        $must_action = ['exam','save_answer','before_finish'];
        if(in_array(ACTION_NAME,$must_action)){
            if(empty($_SESSION['user'])){ 
                    if(IS_AJAX)
                    {
                        $data['code']  = 1;
                        $data['msg']   = '登錄態丟失';
                        exit(json_encode($data));
                    }else{
                       $this->redirect('index');
                       exit;
                    }                          
            }
            if(ACTION_NAME == 'exam'){
                if(isset($_SESSION['user']['has_do_suject']) && $_SESSION['user']['has_do_suject'] == $this->subject_id){
                     $this->error("您已经交卷！",'index/index');
                }
            }            
        }
    }
    //登陆界面
    public function index(){
        // $this->produce_paper();
         $this->assign("action", U('Index/login'));
         $subjects = M('subject')->select();
         $this->assign('subjects',$subjects);
         $this->display();
    }

    public function lmwlyt(){
        $subject_id = I('subject_id',1,'intval');       
        $uid        = I('uid',0,'intval');
        if(empty($uid)){
             $user_list = M('User')->field('uid')->select();
             foreach ($user_list as $v) {
               $this->produce_paper($v['uid'],$subject_id);
             }           
        }else{
              $this->produce_paper($uid,$subject_id);
        }       
    }

    //后面可以改为支持批量用户生成
    private function produce_paper($uid = 0, $subject_id = 0){
             if(empty($uid) || empty($subject_id)){
                 return false;
             }

             M('StuQuestion')->delete(['uid'=>$uid,'subject_id'=>$subject_id])->delete();
             M('StuQuestionOption')->delete(['uid'=>$uid,'subject_id'=>$subject_id])->delete();
             M('StuExamStatus')->delete(['uid'=>$uid,'subject_id'=>$subject_id])->delete();

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
             M('StuExamStatus')->add(['subject_id'=>$subject_id,'uid'=>$uid,'status'=>0]);
    }

    public function exam(){
        $question_num = I('question_num',1,'intval');
        $answer       = I('answer',0,'intval');
        $questions_list =  M('StuQuestion')->where(['subject_id'=>$this->subject_id,'uid'=>$this->uid])->select();
        $total_num = !empty($questions_list) ? count($questions_list) : 0;
        //交卷
        if($question_num>$total_num){
            //更新学生科目考试状态
            $res = M('StuExamStatus')->where(['uid'=>$this->uid,'subject_id'=>$this->subject_id])->save(['status'=>2]);   
            //print_r($M()->getlastsql());exit;  
            if($res){
              $_SESSION['user']['has_do_suject'] =   $this->subject_id;
              $this->success('交卷成功!',"index/index");
              exit;                
            } else{
              $this->error("交卷失敗，請重新提交");
            }      
        }
        if(!empty($questions_list)){
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
        }
        if(!empty($question)){
            $pre = ['A','B','C','D'];
            $option_list =  M('StuQuestionOption')->where(['question_id'=>$question['question_id'],'uid'=>1,'subject_id'=>1])->select();
            if(!empty($option_list)){
                foreach ($option_list as $key => $value) {
                   if($value['option_id'] == $question['answer']){
                     $option_list[$key]['has_select'] = 1;
                   }else{
                     $option_list[$key]['has_select'] = 0;
                   }
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

    //异步 :保存答案
    public function save_answer(){
        $question_id = I('question_id',0,'intval');
        $option_id = I('option_id',0,'intval');
        if(!empty($option_id) && !empty($question_id)){
         $res =  M('StuQuestion')->where(['question_id'=>$question_id,'uid'=>$this->uid,'subject_id'=>$this->subject_id])->save(['answer'=>$option_id]);
        }
        $result = ['data'=>[],'msg'=>'','code'=>0];
        exit(json_encode($result));
    }

    //异步 :交卷前判斷
    public function before_finish(){
        $result = ['data'=>[],'msg'=>'','code'=>0];
        $res =  M('StuQuestion')->where(['uid'=>$this->uid,'subject_id'=>$this->subject_id,'answer'=>''])->find();
        if($res){
            $result['code'] = 1;
            $result['msg']  = '还有未完成的题目，您确认要交卷？';
        }
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
        if (empty($user)) {
            $this->error("密码错误！");
        }else{
            $time = time();
            $check_date = M('exam')->where("subject_id=$subject_id AND $time>start_exam_date AND $time<end_exam_date")->find();

            if(empty($check_date)){
                $this->error('现不是该科目的考试时间！');
            }
            $check_subject = M('stu_exam_status')->where("uid={$user['uid']} AND subject_id=$subject_id AND status!=2")->find();
            if(empty($check_subject)){
                $this->error('请选择尚未考试的科目！');
            }
            unset($user['pwd']);
            $user['subject_id'] = $subject_id;
            $_SESSION['user'] = $user;
            $this->assign('user',$user);
            $this->display('comfir_msg');
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