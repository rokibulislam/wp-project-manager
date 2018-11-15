<?php

$projects = WeDevs\PM\Project\Models\Project::get()->toArray();

?>

<div class="pm-cteate-task pm-global" id="pmcteatetask">
    <div class="inner">
        
        <form class="new-task-form"  id="newtaskform">
            <div class="errors"></div>
            <div >
                <input id="task-title" type="text" placeholder="Task title" />
                
            </div>
            <div class="select-project">
                <select name="project"  >

                    <option value="0"> <?php _e('Select a project', 'wedevs-project-manager')?> </option>

                    <?php
                        foreach ($projects as $project ) {
                            ?>
                            <option value="<?php echo $project['id']; ?>"> <?php  echo $project['title'];  ?> </option>
                            <?php
                        }
                    ?>

                </select>

            </div>
        </form>
        
    </div>
    <div class="pmbackoverlay" ></div>
    
</div>