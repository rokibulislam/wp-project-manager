<template>
    <div class="popup-mask pm-task-to-task-wrap pm-pro-automation pm-pro-import-task-modal">
        <div class="popup-container automation-popup-container">

            <div class="automation-popup-body">
                <div class="head">
                    <span>{{ __('task To Task', 'pm-pro') }}</span>
                </div>
                <div class="content">

                    <div v-if="!isListLoaded" class="loading-animation">
                        <div  class="loading-projects-title">{{ __( 'Loading Task Lists', 'pm-pro') }}</div>
                        <div class="load-spinner">
                            <div class="rect1"></div>
                            <div class="rect2"></div>
                            <div class="rect3"></div>
                            <div class="rect4"></div>
                        </div>
                    </div>


                    <div :class="!isListLoaded ? 'pm-pro-form-wrap list-drop-down' : 'list-drop-down'">
                        <pm-list-drop-down
                            :options="listDropDownOptions"
                            @onChange="setList"
                            @afterGetLists="afterFetchList">

                        </pm-list-drop-down>
                    </div>

                </div>

                <div class="button-group">
                    <div class="button-group-inside">
                        <div class="cancel-btn-wrap">
                            <a href="#" @click.prevent="close()" class="pm-button pm-secondary">{{ __('Cancel', 'pm-pro') }}</a>
                        </div>
                        <div class="update-btn-wrap">
                            <a href="#" @click.prevent="changetasklist()" :class="submitBtnClass()">{{ __('Change Task List', 'pm-pro') }}</a>
                            <div v-if="submitProcessing" class="pm-circle-spinner"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>


<style lang="less">
    .margin-top-10() {
        margin-top: 10px;
    }
    .content-width () {
        width: 500px !important;
    }
    .pm-task-to-task-wrap {
        background: rgba(0, 0, 0, 0.59) !important;
    }
    .pm-pro-import-task-modal {
        .pm-pro-form-wrap {
            background: #eee;
            opacity: 0.3;
            display: none;
        }
        .loading-animation {
            display: flex;
            align-items: center;
            margin-left: 33%;
            color: #000;

            .load-spinner {
                margin: 0;
            }
        }
    }
    .drop-down () {
        min-height: auto;
        margin-right: 8px;

        .multiselect__single {
            margin-bottom: 0;
        }

        .multiselect__input {
            border: none;
            box-shadow: none;
            margin: 0;
            font-size: 14px;
            vertical-align: baseline;
            height: 0;
        }
        .multiselect__element {
            .multiselect__option {
                font-weight: normal;
                white-space: normal;
                padding: 6px 12px;
                line-height: 25px;
                font-size: 14px;
                display: flex;

                .option-image-wrap {
                    .option__image {
                        border-radius: 100%;
                        height: 16px;
                        width: 16px;
                    }
                }
                .option__desc {
                    line-height: 20px;
                    font-size: 13px;
                    margin-left: 5px;
                }
            }

        }
        .multiselect__tags {
            min-height: auto;
            padding: 4px;
            border-color: #ddd;
            border-radius: 3px;
            white-space: normal;
            .multiselect__single {
                font-size: 12px;
            }
            .multiselect__tags-wrap {
                font-size: 12px;
            }

            .multiselect__spinner {
                position: absolute;
                right: 24px;
                top: 14px;
                width: auto;
                height: auto;
                z-index: 99;
            }

            .multiselect__tag {
                margin-bottom: 0;
                overflow: visible;
                border-radius: 3px;
                margin-top: 2px;
            }
        }



    }
    .pm-pro-automation {
        .head {
            .content-width ();
            background-color: #f6f8fa;
            border-bottom: 1px solid #eee;
            padding: 16px;
            font-size: 14px;
            font-weight: 600;
            color: #24292e;
            position: fixed;
            top: 45px;
        }
        .automation-popup-body {
            .content {
                padding: 16px;
                height: 100%;
                .list-drop-down {
                    z-index: 999999;
                }
                .multiselect__single {
                    width: auto;
                }
                .multiselect__spinner {
                    top: 16px !important;
                }
                .multiselect__select {
                    display: block;
                    &:before {
                        position: relative;
                        right: 0;
                        top: 17px;
                        color: #999;
                        margin-top: 4px;
                        border-style: solid;
                        border-width: 5px 5px 0;
                        border-color: #999 transparent transparent;
                        content: "";
                        z-index: 999;
                    }
                }

                .tab-link {
                    border-radius: 3px 0px 0px 3px !important;
                }
                // .checkbox {
                //     height: 16px;
                //     width: 16px;
                // }

                .tasks-wrap {
                    border: 1px solid #f1f1f1;
                    height: 200px;
                    overflow: auto;
                    padding: 10px 10px;
                    color: #555;
                    font-size: 13px;

                    .incomplete {
                        background: #aa4100;
                        padding: 1px 3px;
                        color: #fff;
                        font-size: 10px;
                        margin-left: 10px;
                    }

                    .complete {
                        background: #0073aa;
                        padding: 1px 3px;
                        color: #fff;
                        font-size: 10px;
                        margin-left: 10px;
                    }
                }
                .all-select-wrap {
                    border: 1px solid #f1f1f1;
                    padding: 10px;
                    color: #555;
                    font-size: 13px;
                }

                .task-status-tab {
                    display: flex;
                    justify-content: center;
                    margin: 15px 0 !important;

                    .first {
                        border-radius: 3px 0px 0px 3px !important;
                    }
                    .second {
                        border-radius: 0px !important;
                    }
                    .third {
                        border-radius: 0px 3px 3px 0px !important;
                    }
                }
                .preset-wrap {
                     .preset {
                        color: #24292e;
                        .select-type {
                            .margin-top-10();

                            select {
                                min-width: 135px;
                                color: #24292e;
                                border-color: #ccc;
                                margin-left: 7px;
                            }
                        }
                        .type-header {
                            .margin-top-10();
                        }
                    }

                    .preset-element {
                        .margin-top-10();

                        .lists-drop-down-wrap {
                            margin-top: 5px;
                            margin-bottom: 10px;

                            .drop-down ();
                        }
                    }

                }

                .event-wrap {
                    margin: 15px 0 8px 15px;
                    padding-left: 14px;

                    .event-checkbox {
                        float: left;
                        margin: 5px 0 0 -22px;
                        vertical-align: middle;
                    }

                    .label-title {
                        color: #24292e;
                        font-weight: 600;
                        font-size: 14px;
                    }

                    .note {
                        color: #586069;
                        display: block;
                        font-size: 12px;
                        font-weight: 400;
                        margin: 0;
                    }
                }

                .first-event {
                    margin-top: 10px;
                }

                .none {
                    margin-top: 16px;
                    color: #24292e;
                    font-weight: 400;
                    font-size: 13px;
                }

                .type-header {
                    font-weight: 600;
                    font-size: 14px;
                    padding-bottom: 4px;
                    border-bottom: 1px solid #e1e4e8;
                    color: #24292e;
                }

                .assing-user-wrap {
                    margin-top: 20px;
                    .user-assign-note {
                        margin: 5px 0 0px 7px;
                        font-size: 12px;
                    }

                    .user-drop-down-wrap {
                        margin-left: 7px;
                        margin-top: 5px;
                        margin-bottom: 10px;

                        .drop-down ();
                    }
                }

                .task-status-wrap {
                    .event-wrap {
                        .event-checkbox {
                            border-radius: 100%;
                        }
                    }
                }

            }

            .button-group {
                position: fixed;
                display: block;
                background: #f6f8fa;
                .content-width ();
                border-top: 1px solid #eee;
                padding: 12px;

                .button-group-inside {
                    display: flex;
                    float: right;
                    .cancel-btn-wrap {
                        margin-right: 10px;
                    }
                    .submit-btn-text {
                        color: #199ed4 !important;
                    }
                    .update-btn-wrap {
                        position: relative;
                        .pm-circle-spinner {
                            position: absolute;
                            left: 50%;
                            top: 50%;
                            margin-left: -16px;
                            margin-top: -11px;

                            &:after {
                                height: 10px;
                                width: 10px;
                                border-color: #fff #fff #fff transparent;
                            }
                        }
                    }
                }

            }
        }
    }
    .automation-popup-container {
        .content-width ();
        top: 99px !important;
        height: 76px !important;
        border-radius: 0 !important;

        .automation-popup-body {
            height: 100%;
            width: auto;
        }
    }
</style>

<script>
    import Mixins from './mixin';

    export default {
        props: {
            task: {
                type: [Object],
                default () {
                    return {};
                }
            }
        },
        data () {
            return {
                submitProcessing: false,
                isListLoaded: false,
                listDropDownOptions: {
                    placeholder: __( 'Select Task List', 'pm-pro' )
                },
                list: {}
            }
        },
        mixins: [Mixins],
        methods: {
            close () {
                this.$emit('closeListChangeTaskModal');
            },
            afterFetchList (list) {
                this.isListLoaded = true;
            },
            submitBtnClass () {
                return this.submitProcessing ? 'submit-btn-text update pm-button pm-primary' : 'update pm-button pm-primary';
            },
            setList (list) {
                this.list = list;
            },

            changetasklist () {
                if(this.submitProcessing) {
                    return;
                }

                this.submitProcessing = true;

                var self = this;
                var request = {
                    type: 'post',
                    url: self.base_url + '/pm/v2/projects/'+self.project_id+'/tasks/sorting',
                    data: {
                        list_id: self.list.id,
                        task_id: self.task.id,
                        orders: [
                            {
                                'index': 0,
                                'id': self.task.id
                            }
                        ],
                        receive: 1,
                        'status': true
                    },
                    success (res) {
                        if (res.data.task.data) {
                            self.addTaskMeta(res.data.task.data);
                        }

                        self.$store.commit( 'projectTaskLists/afterDeleteTask', {
                            task: self.task,
                            list: { id: res.data.sender_list_id }
                        });

                        self.$store.commit( 'projectTaskLists/afterNewTask',
                            {
                                list_id: self.list.id,
                                task: res.data.task.data,
                            }
                        );

                        self.close();
                        self.$emit('afterChangetasklist',{
                            res: res,
                            taskId: self.task.id,
                            listId: self.list.id,
                            senderListId: res.data.sender_list_id,
                            senderTask: self.task,
                            task: res.data.task.data
                        });
                    }
                };
                self.httpRequest(request);
            },
        }
    }
</script>
