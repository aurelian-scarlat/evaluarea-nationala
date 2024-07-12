class Raport {

    #path = 'parser/#/raport.json';
    #raport = {};
    #defaultOptions = {
      vAxis: { minValue: 0, maxValue: 10 },
      chartArea: {
        left: '5%',
        right: '5%',
        top: '10%',
        bottom: '20%',
      },
      legend: {
        position: 'bottom',
        maxLines: 3
    },
      animation: {
        duration: 500,
        startup: true,
      }
    }

    #locuri = ['RO', 'RO:URBAN', 'RO:RURAL', 'B', 'B:SECTORUL 1', 'B:SECTORUL 2'];//, 'B:SECTORUL 3', 'B:SECTORUL 4', 'B:SECTORUL 5', 'B:SECTORUL 6'];

    async load(year){
        const response = await fetch(this.#path.replace(/#/, year));
        this.#raport = await response.json();
        this.drawCharts();
    }

    drawCharts(){
        if(!this.#raport.RO || !google.visualization || !google.visualization.ColumnChart){
            return;
        }
        this.drawSingleChart('med', 'Medie');
        this.drawTop(10);
        this.drawTop(25);
        this.drawTop(50);
        this.drawDistrib();
        this.drawPrezenta();
    }

    drawSingleChart(key, name){
        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Loc');
        data.addColumn('number', name);
        data.addColumn({'type': 'number', role: 'annotation' });
        this.#locuri.forEach(loc => {
            data.addRow([this.#raport[loc].nume, this.#raport[loc][key], this.#raport[loc][key]]);
        });

        const chart = new google.visualization.ColumnChart(document.getElementById(key));
        chart.draw(data, this.#defaultOptions);
    }

    drawPrezenta(){
        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Loc');
        data.addColumn('number', 'Prezența');
        data.addColumn({'type': 'string', role: 'annotation' });
        this.#locuri.forEach(loc => {
            const prez = Math.round(this.#raport[loc].prez / (this.#raport[loc].prez + this.#raport[loc].abs) * 10000) / 100;
            data.addRow([this.#raport[loc].nume, prez, prez + '%']);
        });

        const chart = new google.visualization.ColumnChart(document.getElementById('prez'));
        chart.draw(data, this.#defaultOptions);

    }

    drawTop(num){
        const data = new google.visualization.DataTable();
        data.addColumn('string', 'Loc');
        data.addColumn('number', 'Media primilor ' + num + '%');
        data.addColumn({'type': 'number', role: 'annotation' });
        data.addColumn('number', 'Ultima nota a primilor ' + num + '%');
        data.addColumn({'type': 'number', role: 'annotation' });
        this.#locuri.forEach(loc => {
            const prez = Math.round(this.#raport[loc].prez / (this.#raport[loc].prez + this.#raport[loc].abs) * 10000) / 100;
            data.addRow([
                this.#raport[loc].nume,
                this.#raport[loc]['m' + num],
                this.#raport[loc]['m' + num],
                this.#raport[loc]['n' + num],
                this.#raport[loc]['n' + num],
            ]);
        });

        const chart = new google.visualization.ColumnChart(document.getElementById('top' + num));
        chart.draw(data, this.#defaultOptions);

    }

    drawDistrib(){
        document.getElementById('distrib').innerHTML = '';
        this.#locuri.forEach((loc, i) => {
            document.getElementById('distrib').insertAdjacentHTML('beforeend', '<div id="distrib' + i +'" class="chart"></div><br>');
            this.drawOneDistrib(this.#raport[loc], i);
        });
    }

    drawOneDistrib(loc, idx){
        const data = new google.visualization.DataTable();
        data.addColumn('string', loc.nume);
        data.addColumn('number', 'Procent');
        data.addColumn({'type': 'string', role: 'annotation' });
        for(let i = 100; i>=10; i-=5){
            const proc = Math.round(loc['p' + i] / loc.prez * 10000) / 100;
            const label = i == 100 ? '10' : (i / 10) + ' - ' + ((i*10 + 49) / 100);
            data.addRow([label, proc, proc + '%']);
        }

        const options = Object.assign({ title: loc.nume}, this.#defaultOptions);
        const chart = new google.visualization.ColumnChart(document.getElementById('distrib' + idx));
        chart.draw(data, options);
    }

}


function changeYear(year){
    raport.load(year);
    document.getElementById('nav' + year).className = 'active';
    document.getElementById('nav' + (year == 2024 ? 2023 : 2024)).className = '';
}

/**
 * Init Raport
 */
raport = new Raport();
raport.load(2024);


/**
 * Init Google Charts
 */
google.charts.load('current', {packages: ['corechart']});
google.charts.setOnLoadCallback(() => raport.drawCharts());

