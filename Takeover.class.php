<?php

class Takeover extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();
        if ($GLOBALS['perm']->have_perm("dozent")
                && !$GLOBALS['perm']->have_perm("admin")
                && stripos($_SERVER['REQUEST_URI'], "dispatch.php/course/details") !== false) {
            NotificationCenter::addObserver($this, "addLink", "SidebarWillRender");
        }
    }

    public function addLink()
    {
        $sem_id = Request::option("sem_id", Context::get()->id);
        if (!$sem_id) {
            $prefix = "dispatch.php/course/details/index/";
            $pos = stripos($_SERVER['REQUEST_URI'], $prefix) + strlen($prefix);
            $sem_id = substr($_SERVER['REQUEST_URI'], $pos);
        }
        if (!$GLOBALS['perm']->have_studip_perm("dozent", $sem_id) && $this->takeoverAllowed($sem_id)) {
            $actions = Sidebar::get()->getWidget("actions");

            $actions->addLink(
                _("Veranstaltung übernehmen"),
                PluginEngine::getURL($this, array("sem_id" => $sem_id), "takeit"),
                Icon::create("door-enter2", "clickable")
            );
        }
    }

    public function takeit_action()
    {
        if (!$this->takeoverAllowed(Request::option("sem_id"))) {
            throw new AcessDeniedException();
        }
        $course = new Seminar(Request::option("sem_id"));
        $course->addMember($GLOBALS['user']->id, "dozent");
        PageLayout::postMessage(MessageBox::success(_("Sie wurden der Veranstaltung als Dozent hinzugefügt. Bitte bearbeiten Sie die Veranstaltung jetzt und tragen etwaige Dummy-Dozenten wieder aus.")));
        header("Location: ".URLHelper::getURL("dispatch.php/course/members", array('cid' => Request::option("sem_id"))));
        exit;
    }

    protected function takeoverAllowed($course_id)
    {
        if (!$GLOBALS['perm']->have_perm("dozent")
            || $GLOBALS['perm']->have_perm("admin")) {
            return false;
        }
        $course = Course::find($course_id);
        if (SeminarCategories::getByTypeId($course->status)->only_inst_user) {
            $user_institute_stmt = DBManager::get()->prepare("
                SELECT Institut_id
                FROM user_inst
                WHERE user_id = :user_id
            ");
            $user_institute_stmt->execute(array('user_id' => $GLOBALS['user']->id));
            $user_institute = $user_institute_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!in_array($this->institut_id, $user_institute)
                    && !count(array_intersect($user_institute, $course->institutes->pluck("institut_id")))) {
                return false;
            }
        }
        return true;
    }
}
