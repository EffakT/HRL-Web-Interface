<template>
    <data-table
        :maps="maps"
        :columns="columns"
        :url="ajax"
        :classes="classes"
        :filters="filters"
        order-by="time">
        <div slot="filters" slot-scope="{ tableData, perPage }">
            <div class="row mb-2">
                <div class="col-md-4">
                    <select class="form-control" v-model="tableData.length">
                        <option :key="page" v-for="page in perPage">{{ page }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select
                        v-model="tableData.filters.map"
                        class="form-control">
                        <option value="">All</option>
                        <option v-for="map in maps" v-bind:value="map.id">
                            {{ map.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input
                        name="name"
                        class="form-control"
                        v-model="tableData.search"
                        placeholder="Search Table">
                </div>
            </div>
        </div>
        <tbody slot="body" slot-scope="{ data }">
        <tr
            :key="item.id"
            @click="goToItem(item)"
            v-for="item in data" style="cursor: pointer">
            <td
                :key="column.name"
                v-for="column in columns">
                <data-table-cell
                    :value="item"
                    :name="column.name"
                    :meta="column.meta"
                    :comp="column.component"
                    :classes="column.classes">
                </data-table-cell>
            </td>
        </tr>
        </tbody>
    </data-table>
</template>

<script>
    // import viewButton from "./viewButton";

    export default {
        props: {
            route: {type: String, required: true},
            ajax: {type: String, required: true},
            maps: {type: Array, required: true}
        },
        mounted() {
        },
        data() {
            return {
                columns: [
                    {
                        label: 'Player',
                        name: 'player.name',
                        columnName: 'players.name',
                        orderable: true,
                    },
                    {
                        label: 'Map',
                        name: 'map.label',
                        columnName: 'maps.label',
                        orderable: true,
                    },
                    {
                        label: 'Time (seconds)',
                        name: 'time',
                        orderable: true,
                    },
                    {
                        label: 'Date of Lap',
                        name: 'created_at',
                        orderable: true,
                    },
                    /*{
                        label: '',
                        name: 'View',
                        orderable: false,
                        classes: {
                            'btn': true,
                            'btn-primary': true,
                            'btn-sm': true
                        },
                        event: "click",
                        handler: this.onClick,
                        component: viewButton,
                    }*/
                ],
                classes: {
                    "table-container": {
                        "table-responsive": true,
                    },
                    "table": {
                        "table": true,
                        "table-striped": false,
                        "table-dark": false,
                        "table-hover": true,
                    },
                    "t-head": {},
                    "t-body": {},
                    "t-head-tr": {},
                    "t-body-tr": {},
                    "td": {},
                    "th": {},
                },
                filters: {
                    map: '',
                },
            }
        },
        /*components: {
            viewButton,
        },*/
        methods: {
            goToItem(data) {
                var url = this.route.replace('player_id', data.player_id);
                window.location.href = url;
            },
        },
    }
</script>
