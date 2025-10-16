<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>
        <div class="mx-auto">
            <div class="grid sm:grid-cols-1 md:grid-cols-4 gap-2">
                <div class="">
                    <div
                        class="relative flex flex-col min-w-0 break-words bg-white rounded mb-6 xl:mb-0 shadow-lg">
                        <div class="flex-auto p-4">
                            <div class="flex flex-wrap">
                                <div class="relative w-full pr-4 max-w-full flex-grow flex-1"><h5
                                    class="text-gray-500 uppercase font-bold text-xs"> COURSES </h5>
                                    <span
                                        class="font-semibold text-xl text-gray-800"> {{ courses }} </span>
                                </div>
                                <div class="relative w-auto pl-4 flex-initial">
                                    <div
                                        class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 shadow-lg rounded-full bg-blue-500">
                                        <font-awesome-icon icon="graduation-cap"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <div
                        class="relative flex flex-col min-w-0 break-words bg-white rounded mb-6 xl:mb-0 shadow-lg">
                        <div class="flex-auto p-4">
                            <div class="flex flex-wrap">
                                <div class="relative w-full pr-4 max-w-full flex-grow flex-1"><h5
                                    class="text-gray-500 uppercase font-bold text-xs"> LOANS APPLIED </h5>
                                    <span
                                        class="font-semibold text-xl text-gray-800"> {{ loans }} </span>
                                </div>
                                <div class="relative w-auto pl-4 flex-initial">
                                    <div
                                        class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 shadow-lg rounded-full bg-green-500">
                                        <font-awesome-icon icon="money-bill"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="">
                    <div
                        class="relative flex flex-col min-w-0 break-words bg-white rounded mb-6 xl:mb-0 shadow-lg">
                        <div class="flex-auto p-4">
                            <div class="flex flex-wrap">
                                <div class="relative w-full pr-4 max-w-full flex-grow flex-1"><h5
                                    class="text-gray-500 uppercase font-bold text-xs"> Rejected Loans </h5>
                                    <span
                                        class="font-semibold text-xl text-gray-800"> {{ loansRejected }} </span>
                                </div>
                                <div class="relative w-auto pl-4 flex-initial">
                                    <div
                                        class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 shadow-lg rounded-full bg-red-500">
                                        <font-awesome-icon icon="minus"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="">
                    <div
                        class="relative flex flex-col min-w-0 break-words bg-white rounded mb-6 xl:mb-0 shadow-lg">
                        <div class="flex-auto p-4">
                            <div class="flex flex-wrap">
                                <div class="relative w-full pr-4 max-w-full flex-grow flex-1"><h5
                                    class="text-gray-500 uppercase font-bold text-xs"> Approved Loans Amount </h5>
                                    <span
                                        class="font-semibold text-xl text-gray-800"> {{ loansApprovedAmount }} </span>
                                </div>
                                <div class="relative w-auto pl-4 flex-initial">
                                    <div
                                        class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 shadow-lg rounded-full bg-green-500">
                                        <font-awesome-icon icon="dollar-sign"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex mt-4 bg-white rounded p-4 mb-6 xl:mb-0 shadow-lg">
                <div class="w-full">
                    <FullCalendar :options="calendarOptions"/>
                </div>
            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Pages/MemberPortal/Layouts/AppLayout.vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'

export default {
    components: {
        AppLayout,
        FullCalendar,
    },
    props: {
        loans: String,
        loansApproved: String,
        loansRejected: String,
        loansApprovedAmount: String,
        courses: String,

    },
    data() {
        return {
            calendarOptions: {
                plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin, listPlugin],
                headerToolbar: {
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,list',
                    left: 'prev,next,today',
                    center: 'title',
                },
                initialView: 'dayGridMonth',
                selectable: true,
                unselectAuto: true,
                selectMirror: true,
                select: this.handleSelect,
                events: []
            },
            events: [],
            pageTitle: "Dashboard",
            pageDescription: "Dashboard",
            width: 0,
            height: 0,
            top: 0,
            left: 0
        }
    },
    mounted() {
        this.getAppointments();
    },
    methods: {
        resize(newRect) {
            this.width = newRect.width;
            this.height = newRect.height;
            this.top = newRect.top;
            this.left = newRect.left;
        },
        getAppointments() {
            axios.get(this.route('portal.events.get_events')).then(response => {
                this.events = response.data;
                this.events.forEach(item => {
                    this.calendarOptions.events.push({
                        id: item.id,
                        title: item.title ,
                        allDay: item.all_day,
                        start: item.event_start_date,
                        end: item.event_end_date,
                        url: route('portal.events.show', item.id),
                    });
                });
            })
        }
    }
}
</script>
