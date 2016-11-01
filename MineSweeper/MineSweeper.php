<?php
/**
 * 扫雷
 */
class MineSweeper extends GtkWindow {
    private $column; // 表格列数
    private $row; // 表格行数
    private $mine; // 地雷数量
    private $grid_size; // 格子的大小
    private $grids; // 所有的格子
    private $active_qty; // 已经激活的格子数
    private $cur_grid; // 当前点击的格子
    private $vbox;
    private $table;

    /**
     * 构造方法
     */
    public function __construct($column = 9, $row = 9, $mine = 10, $grid_size = 40) {
        parent::__construct();

        $this->column     = $column;
        $this->row        = $row;
        $this->mine       = $mine;
        $this->grid_size  = $grid_size;
        $this->grids      = array();
        $this->active_qty = 0;
        $this->cur_grid   = null;

        $menubar   = $this->buildMenubar();
        $table     = $this->buildTable();
        $statusbar = $this->buildStatusbar();

        $vbox = new GtkVBox();
        $vbox->pack_start($menubar, false, false);
        $vbox->pack_start($table, true, true);
        $vbox->pack_start($statusbar, false, false);

        $this->vbox  = $vbox;
        $this->table = $table;

        $this->add($vbox);
        $this->set_title('扫雷');
        $this->set_resizable(false);
        $this->connect_simple('destroy', array('Gtk', 'main_quit'));
        $this->show_all();
    }

    /**
     * 构建菜单栏
     * @return GtkMenuBar
     */
    private function buildMenubar() {
        // 菜单栏中的菜单项
        $item_file = new GtkMenuItem('文件(_F)');
        $item_help = new GtkMenuItem('帮助(_H)');

        // 菜单
        $menu_file = new GtkMenu();
        $menu_help = new GtkMenu();
        $item_file->set_submenu($menu_file);
        $item_help->set_submenu($menu_help);

        // 菜单中的菜单项
        $sub_new   = new GtkImageMenuItem('新游戏');
        $sub_quit  = new GtkImageMenuItem('退出');
        $sub_about = new GtkImageMenuItem('关于');
        $sub_new->connect_simple('activate', array($this, 'restart'));
        $sub_quit->connect_simple('activate', array('Gtk', 'main_quit'));
        $menu_file->append($sub_new);
        $menu_file->append($sub_quit);
        $menu_help->append($sub_about);

        // 菜单栏
        $menubar = new GtkMenuBar();
        $menubar->add($item_file);
        $menubar->add($item_help);

        return $menubar;
    }

    /**
     * 构建表格
     * @return GtkTable
     */
    private function buildTable() {
        $table = new GtkTable();
        for ($r = 0; $r < $this->row; $r++) {
            for ($c = 0; $c < $this->column; $c++) {
                $grid = new GtkToggleButton();
                $grid->set_size_request($this->grid_size, $this->grid_size);
                $grid->set_focus_on_click(false);
                $grid->connect('button-press-event', array($this, 'onPressGrid'));
                $grid->connect('clicked', array($this, 'onClickGrid'));
                $grid->row           = $r;
                $grid->column        = $c;
                $this->grids[$r][$c] = $grid;
                $table->attach($this->grids[$r][$c], $c, $c + 1, $r, $r + 1, Gtk::EXPAND | Gtk::FILL, Gtk::EXPAND | Gtk::FILL, 0, 0);
            }
        }
        $this->buildGrids();
        return $table;
    }

    /**
     * 构建状态栏
     * @return GtkStatusbar
     */
    private function buildStatusbar() {
        $statusbar = new GtkStatusBar();
        $statusbar->push($statusbar->get_context_id("message"), "{$this->column}*{$this->row}个格子 共{$this->mine}个地雷");
        return $statusbar;
    }

    /**
     * 构建格子
     */
    private function buildGrids() {
        if (!$this->grids) {
            return;
        }

        // 清除所有状态
        foreach ($this->grids as $grid) {
            foreach ($grid as $g) {
                $g->mine_qty  = null;
                $g->is_mine   = null;
                $g->is_marked = null;
            }
        }

        // 设置地雷
        for ($i = 0; $i < $this->mine; $i++) {
            $row    = rand(0, $this->row - 1);
            $column = rand(0, $this->column - 1);
            if (!$this->grids[$row][$column]->is_mine) {
                $this->grids[$row][$column]->is_mine = true;
            } else {
                $i--;
            }
        }

        // 设置周围雷数
        for ($r = 0; $r < $this->row; $r++) {
            for ($c = 0; $c < $this->column; $c++) {
                if ($this->grids[$r][$c]->is_mine) {
                    continue;
                }
                $count = 0;
                if ($r - 1 >= 0 && $c - 1 >= 0 && $this->grids[$r - 1][$c - 1]->is_mine) {
                    $count++;
                }
                if ($r - 1 >= 0 && $this->grids[$r - 1][$c]->is_mine) {
                    $count++;
                }
                if ($r - 1 >= 0 && $c + 1 <= $this->column - 1 && $this->grids[$r - 1][$c + 1]->is_mine) {
                    $count++;
                }
                if ($c - 1 >= 0 && $this->grids[$r][$c - 1]->is_mine) {
                    $count++;
                }
                if ($c + 1 <= $this->column - 1 && $this->grids[$r][$c + 1]->is_mine) {
                    $count++;
                }
                if ($r + 1 <= $this->row - 1 && $c - 1 >= 0 && $this->grids[$r + 1][$c - 1]->is_mine) {
                    $count++;
                }
                if ($r + 1 <= $this->row - 1 && $this->grids[$r + 1][$c]->is_mine) {
                    $count++;
                }
                if ($r + 1 <= $this->row - 1 && $c + 1 <= $this->column - 1 && $this->grids[$r + 1][$c + 1]->is_mine) {
                    $count++;
                }
                $this->grids[$r][$c]->mine_qty = $count;
            }
        }
    }

    /**
     * 游戏结束
     * @param  bool $isWin 是否胜利
     */
    private function end($isWin) {
        // 显示所有地雷
        foreach ($this->grids as $grid) {
            foreach ($grid as $g) {
                if ($g->is_mine) {
                    $g->set_image(GtkImage::new_from_file(dirname(__FILE__) . "/imgs/mine.png"));
                }
            }
        }

        $dialog = new GtkDialog($isWin ? "游戏胜利" : "游戏失败", $this, Gtk::DIALOG_MODAL);
        $dialog->set_default_size(380, 100);
        $dialog->connect_simple('destroy', array($this, 'restart'));
        $dialog->vbox->add(new GtkLabel($isWin ? "恭喜！您赢了！" : "不好意思，您输了。下次走运！"));
        $dialog->add_button("新开一局", Gtk::RESPONSE_OK);
        $dialog->add_button("退出", Gtk::RESPONSE_CLOSE);
        $dialog->show_all();
        $result = $dialog->run();
        switch ($result) {
            case (Gtk::RESPONSE_OK):
                $this->restart();
                break;
            case (Gtk::RESPONSE_CLOSE):
                Gtk::main_quit();
                break;
        }
        $dialog->destroy();
    }

    /**
     * 格子右键点击事件处理
     * 如果未激活格子，标记格子或移除标记
     * @param  GtkToggleButton $grid  所右击的格子
     * @param  GdkEvent        $event 事件对象
     */
    public function onPressGrid($grid, $event) {
        if (3 == $event->button && !$grid->get_active()) {
            if ($grid->is_marked) {
                $grid->set_image(GtkImage::new_from_file(dirname(__FILE__) . "/imgs/transparent.png"));
                $grid->is_marked = false;
            } else {
                $grid->set_image(GtkImage::new_from_file(dirname(__FILE__) . "/imgs/mark.png"));
                $grid->is_marked = true;
            }
        }
    }

    /**
     * 格子点击事件处理
     * @param  GtkToggleButton $grid  所点击的格子
     */
    public function onClickGrid($grid) {
        // 已标记不处理
        if ($grid->is_marked) {
            $grid->set_active(false);
            return;
        }

        if ($this->cur_grid == $grid) {
            $this->cur_grid = null;
            return;
        }

        if (!$grid->get_active()) {
            // 激活后再次点击不恢复到未激活状态
            $this->cur_grid = $grid;
            $grid->set_active(true);
        } else {
            $this->active_qty++;

            // 显示对应图片
            $img = strval($grid->mine_qty);
            $img = "0" === $img ? "transparent" : $img;
            $img = $grid->is_mine ? "mine" : $img;
            $grid->set_image(GtkImage::new_from_file(dirname(__FILE__) . "/imgs/$img.png"));

            // 如果是空白格子，掀开周围格子
            if (0 === $grid->mine_qty) {
                $r = $grid->row;
                $c = $grid->column;
                if ($r - 1 >= 0 && $c - 1 >= 0 && !$this->grids[$r - 1][$c - 1]->get_active()) {
                    $this->grids[$r - 1][$c - 1]->clicked();
                }
                if ($r - 1 >= 0 && !$this->grids[$r - 1][$c]->get_active()) {
                    $this->grids[$r - 1][$c]->clicked();
                }
                if ($r - 1 >= 0 && $c + 1 <= $this->column - 1 && !$this->grids[$r - 1][$c + 1]->get_active()) {
                    $this->grids[$r - 1][$c + 1]->clicked();
                }
                if ($c - 1 >= 0 && !$this->grids[$r][$c - 1]->get_active()) {
                    $this->grids[$r][$c - 1]->clicked();
                }
                if ($c + 1 <= $this->column - 1 && !$this->grids[$r][$c + 1]->get_active()) {
                    $this->grids[$r][$c + 1]->clicked();
                }
                if ($r + 1 <= $this->row - 1 && $c - 1 >= 0 && !$this->grids[$r + 1][$c - 1]->get_active()) {
                    $this->grids[$r + 1][$c - 1]->clicked();
                }
                if ($r + 1 <= $this->row - 1 && !$this->grids[$r + 1][$c]->get_active()) {
                    $this->grids[$r + 1][$c]->clicked();
                }
                if ($r + 1 <= $this->row - 1 && $c + 1 <= $this->column - 1 && !$this->grids[$r + 1][$c + 1]->get_active()) {
                    $this->grids[$r + 1][$c + 1]->clicked();
                }
            }

            // 失败
            if ($grid->is_mine) {
                $this->end(false);
                return;
            }
            // 胜利
            if ($this->active_qty + $this->mine == $this->column * $this->row) {
                $this->end(true);
                return;
            }
        }
    }

    /**
     * 重新开始游戏
     */
    public function restart() {
        $this->grids      = array();
        $this->active_qty = 0;
        $this->cur_grid   = null;

        $this->vbox->remove($this->table);
        $this->table = $this->buildTable();
        $this->vbox->pack_start($this->table, true, true);
        $this->vbox->reorder_child($this->table, 1);
        $this->show_all();
    }
}
new MineSweeper();
Gtk::main();