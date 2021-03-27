<template>
    <data-table
        :columns="columns"
        :url="ajax"
        :classes="classes"
        order-by="time">
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
            ajax: {type: String, required: true}
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
                        label: 'Server',
                        name: 'server.name',
                        columnName: 'servers.name',
                        orderable: true,
                    },
                    {
                        label: 'IP',
                        name: 'server.ip',
                        columnName: 'servers.ip',
                        orderable: true,
                    },
                    {
                        label: 'Port',
                        name: 'server.port',
                        columnName: 'servers.port',
                        orderable: true,
                    },
                    {
                        label: 'Type',
                        name: 'server.type',
                        columnName: 'servers.type',
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
                }
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
