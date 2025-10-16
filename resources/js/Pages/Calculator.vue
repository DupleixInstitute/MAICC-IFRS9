<template>
    <div class="w-full">
        <div
            class="mx-auto overflow-hidden mt-10 shadow-lg mb-2 bg-purple-900 shadow-lg border rounded-lg ">
            <div class="">
                <div class="p-5 text-white text-center text-3xl bg-purple-900"><span
                    class="text-orange-500">Calcu</span>lator
                </div>
                <div class="pt-16 p-5 pb-0 text-white text-right text-3xl bg-purple-800">{{ current }}</div>
                <div class="p-5 text-white text-right text-3xl bg-purple-800">= <span
                    class="text-orange-500">{{ total }}</span></div>


                <div class="flex items-stretch bg-purple-900 h-24">
                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="clear"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            AC
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('(')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            (
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator(')')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            )
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('/')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            ÷
                        </button>
                    </div>
                </div>

                <div class="flex items-stretch bg-purple-900 h-24">
                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(7)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            7
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(8)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            8
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(9)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            9
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('*')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            ×
                        </button>
                    </div>
                </div>

                <div class="flex items-stretch bg-purple-900 h-24">
                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(4)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            4
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(5)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            5
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(6)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            6
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('-')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            -
                        </button>
                    </div>
                </div>

                <div class="flex items-stretch bg-purple-900 h-24">
                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(1)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            1
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(2)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            2
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(3)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            3
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('+')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            +
                        </button>
                    </div>
                </div>


                <div class="flex items-stretch bg-purple-900 h-24 mb-4">
                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputOperator('%')"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            %
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="inputNumber(0)"
                                class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            0
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button
                            class="rounded-full h-20 w-20 flex items-center bg-purple-800 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            .
                        </button>
                    </div>

                    <div
                        class="flex-1 px-2 py-2 justify-center flex items-center text-white text-2xl font-semibold">
                        <button @click="calculate"
                                class="rounded-full h-20 w-20 flex items-center bg-orange-500 justify-center shadow-lg border-2 border-purple-700 hover:border-2 hover:border-gray-500 focus:outline-none">
                            =
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            current: '',
            total: '',
        }
    },
    methods: {
        clear() {
            this.current = "";
            this.total = "";
        },
        inputNumber(number) {
            this.current += number;
        },
        inputOperator(operator) {
            if (this.current === "" && operator != "-") {
                return;
            } else if (this.current[this.current.length - 1] === " ") {
                this.current = this.current.substring(0, this.current.length - 2) + operator + " ";
            } else {
                this.current += " " + operator + " ";
            }
        },
        calculate() {
            if (this.current === "") return;
            this.total = this.expressionParser(this.current);
            //this.current = "";
        },
        expressionParser(expression) {
            let numbers = [];
            let ops = [];
            expression = expression.replace(" ", "");
            if (isNaN(expression[expression.length - 2]) && expression.length != 1) {
                expression += "0";
            }
            for (let i = 0; i < expression.length; i++) {
                if (isNaN(expression[i])) {
                    let currOp = expression[i];
                    while (ops.length > 0 && this.getPriority(currOp) <= this.getPriority(ops[ops.length - 1])) {
                        this.process(numbers, ops[ops.length - 1]);
                        ops.pop();
                    }
                    ops.push(currOp);
                } else {
                    let number = "";
                    while (i < expression.length && !isNaN(expression[i])) {
                        number += expression[i];
                        i++;
                    }
                    i--;
                    numbers.push(number);
                }
            }
            while (ops.length > 0) {
                this.process(numbers, ops[ops.length - 1]);
                ops.pop();
            }
            return numbers[0];
        },
        process(stack, op) {

            let right = parseInt(stack.pop());
            let left = parseInt(stack.pop());
            let value = 0;
            switch (op) {
                case '+':
                    value = left + right;
                    break;
                case '-':
                    value = left - right;
                    break;
                case '*':
                    value = left * right;
                    break;
                case '/':
                    value = Math.floor(left / right);
                    break;
            }
            console.log(value);
            stack.push(value);
        },
        getPriority(op) {
            if (op === "+" || op === "-") return 1;
            else if (op === "*" || op === "/") return 2;
        },
    }
}
</script>

<style scoped>

</style>
