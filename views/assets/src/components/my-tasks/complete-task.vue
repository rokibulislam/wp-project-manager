<template>
    <div class="mytask-current">
        <table class="wp-list-table widefat fixed striped posts completed-task-table">
            <thead>
                <tr>
                    <td @click.prevent="activeSorting('title')" class="pointer">
                        {{ __('Tasks', 'wedevs-project-manager') }}
                        <span class="sort-wrap">
                            <i 
                                :class="sorting.title.asc ? 'active-sorting pm-icon flaticon-caret-down' : 'pm-icon flaticon-caret-down'" 
                                aria-hidden="true">
                                
                            </i>
                            <i 
                                :class="sorting.title.desc ? 'active-sorting pm-icon flaticon-sort' : 'pm-icon flaticon-sort'" 
                                aria-hidden="true">
                                    
                            </i>
                        </span>
                    </td>
                    <td>{{ __('Task List', 'wedevs-project-manager') }}</td>
                    <td>{{ __('Projects', 'wedevs-project-manager') }}</td>
                    <td @click.prevent="activeSorting('completed_at')" class="pointer">
                        {{ __('Completed at', 'wedevs-project-manager') }}
                        <span class="sort-wrap">
                            <i 
                                :class="sorting.completed_at.asc ? 'active-sorting pm-icon flaticon-caret-down' : 'pm-icon flaticon-caret-down'" 
                                aria-hidden="true">
                                    
                            </i>
                            <i 
                                :class="sorting.completed_at.desc ? 'active-sorting pm-icon flaticon-sort' : 'pm-icon flaticon-sort'" 
                                aria-hidden="true">
                                    
                            </i>
                        </span>
                    </td>
                    
                </tr>
            </thead>
            <tbody>
                <tr v-if="tasks.length" v-for="task in tasks">
                    <td><a href="#" @click.prevent="popuSilgleTask(task)">{{ task.title }}</a></td>
                    <td>
                        <router-link
                          :to="{
                            name: 'single_list',
                            params: {
                                project_id: task.project_id,
                                list_id: task.task_list_id
                            }
                        }">
                            {{ task.task_list.title }}
                        </router-link>

                    </td>
                    <td>
                        <router-link
                          :to="{
                            name: 'task_lists',
                            params: {
                                project_id: task.project_id,
                            }
                        }">
                            {{ task.project.title }}
                        </router-link>
                    </td>
                    <td>{{ getDate(task) }}</td>
                </tr>
                <tr v-if="!tasks.length">
                    <td colspan="4">{{ __('No task found!', 'wedevs-project-manager') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="parseInt(individualTaskId) && parseInt(individualProjectId)">
            <single-task :taskId="parseInt(individualTaskId)" :projectId="parseInt(individualProjectId)"></single-task>
        </div>
        <router-view name="singleTask"></router-view>
    </div>
</template>

<style lang="less">
    .mytask-current {
        .completed-task-table {
            .id-td {
                width: 85px;
            }
            .pointer {
                cursor: pointer;
            }
            thead td {
                position: relative;
            }
            .sort-wrap {
                position: absolute;
                right: 25px;
                display: flex;
                top: 50%;
                flex-direction: column;
                transform: translate(10px, -50%);
                i {
                    line-height: 0;
                    transform: rotate(180deg);
                    &:not(.active-sorting) {
                        color: #b5b5b5;
                    }
                    &:before {
                        font-size: 8px !important;

                    }

                }
            }   
        }
    }
</style>
<script>
    export default {
        props: {
            tasks: {
                type: [Array],
                default () {
                    return [];
                }
            }
        },

        data () {
            return {
                individualTaskId: 0,
                individualProjectId: 0,
                sorting: {
                    // id: {
                    //     asc: true,
                    //     desc: false
                    // },
                    title: {
                        asc: false,
                        desc: false
                    },
                    due_date: {
                        asc: false,
                        desc: false
                    },
                    completed_at: {
                        asc: false,
                        desc: false
                    }
                }
            }
        },

        components: {
            'single-task': pm.SingleTask,
        },

        created () {
            pmBus.$on('pm_after_close_single_task_modal', this.afterCloseSingleTaskModal);
            pmBus.$on('pm_generate_task_url', this.generateTaskUrl);
        },

        methods: {
            activeSorting(key) {
                var self = this;
                
                jQuery.each(this.sorting, function( index, val ) {
                    if(index != key) {
                        self.sorting[index].asc = false;
                        self.sorting[index].desc = false;
                    }
                })

                if( !self.sorting[key].asc && !self.sorting[key].desc) {
                    self.sorting[key].asc = true;
                } else {
                    self.sorting[key].asc = self.sorting[key].asc ? false : true;
                    self.sorting[key].desc = self.sorting[key].desc ? false : true;
                } 

                if(self.sorting[key].asc === true) {
                    self.$emit('columnSorting', {
                        orderby: key,
                        order: 'asc'
                    });
                }

                if(self.sorting[key].desc === true) {
                    self.$emit('columnSorting', {
                        orderby: key,
                        order: 'desc'
                    });
                }
            },
            getDate(task) {
                if(typeof task.completed_at != 'undefined' && task.completed_at != '') {
                    return pm.Moment( task.completed_at ).format( 'MMM DD, YYYY' );
                }

                return '';
                
            },
            goToProject(task) {
                this.$router.push({
                    name: 'task_lists',
                    params: { 
                        project_id: task.project_id,
                    }
                });
            },
            goToSigleList (task) {
                this.$router.push({
                    name: 'single_list',
                    params: { 
                        project_id: task.project_id,
                        list_id: task.task_list_id
                    }
                });
            },
            afterCloseSingleTaskModal () {
                this.individualTaskId = false;
                this.individualProjectId = false;
            },
            popuSilgleTask (task) {
                this.individualTaskId = task.id;
                this.individualProjectId = task.project_id;
            },
        }
    }
</script>
