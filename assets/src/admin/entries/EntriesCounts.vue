<template>
    <div>
        <div class="dcf-form-list">
            <div class="dcf-form-list-item" v-for="item in items">
                <h3 class="dcf-form-item-title">{{item.form_title}}</h3>
                <ul class="shapla-status-list">
                    <li v-for="status in getStatuses(item.counts)" :key="status.key" class="shapla-status-list__item"
                        :class="{'is-active':status.active}">
                        <a href="#" @click.prevent="changeStatus(item.form_id,status)"
                           class="shapla-status-list__item-link">
                            <span class="shapla-status-list__item-label">{{status.label}}</span>
                            <span class="shapla-status-list__item-count">({{status.count}})</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
    import {CrudMixin} from "../../components/CrudMixin";
    import statusList from "shapla-data-table-status";

    export default {
        name: "EntriesCounts",
        components: {statusList},
        mixins: [CrudMixin],
        data() {
            return {
                items: [],
            }
        },
        mounted() {
            this.$store.commit('SET_LOADING_STATUS', false);
            this.getEntriesCounts();
        },
        methods: {
            getEntriesCounts() {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.get_items(dcfSettings.restRoot + '/entries/status').then(data => {
                    this.items = data;
                    this.$store.commit('SET_LOADING_STATUS', false);
                }).catch(error => {
                    console.log(error);
                    this.$store.commit('SET_LOADING_STATUS', false);
                });
            },
            getStatuses(counts) {
                return [
                    {key: 'all', label: 'All', count: counts['all']},
                    {key: 'read', label: 'Read', count: counts['read']},
                    {key: 'unread', label: 'Unread', count: counts['unread']},
                    {key: 'trash', label: 'Trash', count: counts['trash']},
                ];
            },
            changeStatus(form_id, status) {
                this.$router.push({name: 'EntriesList', params: {form_id: form_id, status: status.key}})
            }
        }
    }
</script>

<style scoped lang="scss">
    .dcf-form-list {
        box-sizing: border-box;

        > * {
            box-sizing: border-box;
        }

        &-item {
            background: white;
            display: flex;
            flex-direction: column;
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 20px;
            text-align: center;
        }
    }
</style>