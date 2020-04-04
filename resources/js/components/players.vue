<template>
    <data-table
        :columns="columns"
        :url="ajax"
        :classes="classes"
        order-by="name">

        <tbody slot="body" slot-scope="{ data }">
        <tr
            :key="item.id"
            @click="goToItem(item)"
            v-for="item in data">
            <td
                :key="column.name"
                v-for="column in columns" style="cursor: pointer">
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
    import viewButton from "./viewButton";

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
                        label: 'Name',
                        name: 'name',
                        orderable: true,
                    }
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
        components: {
            viewButton,
        },
        methods: {
            goToItem(data) {
                var url = this.route.replace('player_id', data.id);
                window.location.href = url;
            },
        },
    }
</script>
