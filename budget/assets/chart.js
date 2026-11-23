/**
 * Минимальный SVG-график для раздела «Анализ».
 *
 * Без внешних зависимостей: PWA бюджета должна работать офлайн,
 * а CDN-библиотеки (Chart.js и т.п.) здесь не нужны.
 *
 * Использование:
 *   BudgetChart.render(containerEl, {buckets: [{label, label_full}], series: [{name, values}]});
 */
(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var PALETTE = [
        '#8B7355', '#2E7D32', '#1565C0', '#C62828', '#6A1B9A', '#EF6C00',
        '#00838F', '#9E9D24', '#5D4037', '#0277BD', '#AD1457', '#558B2F'
    ];

    var HEIGHT = 240;
    var PAD = { left: 46, right: 12, top: 12, bottom: 26 };

    function svgEl(name, attrs) {
        var node = document.createElementNS(NS, name);
        Object.keys(attrs || {}).forEach(function (key) {
            node.setAttribute(key, attrs[key]);
        });
        return node;
    }

    function fmtCompact(value) {
        var v = Math.abs(value);
        if (v >= 1000000) {
            return (value / 1000000).toFixed(v >= 10000000 ? 0 : 1).replace('.', ',') + ' млн';
        }
        if (v >= 1000) {
            return (value / 1000).toFixed(v >= 10000 ? 0 : 1).replace('.', ',') + ' тыс';
        }
        return String(Math.round(value));
    }

    function fmtFull(value) {
        return Math.round(value).toLocaleString('ru-RU') + ' ₽';
    }

    /** Округление верхней границы оси до «красивого» значения. */
    function niceMax(value) {
        if (value <= 0) {
            return 1;
        }
        var exp = Math.pow(10, Math.floor(Math.log10(value)));
        var base = value / exp;
        var mult = base <= 1 ? 1 : base <= 2 ? 2 : base <= 5 ? 5 : 10;
        return mult * exp;
    }

    function clear(container) {
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
    }

    function emptyState(container, text) {
        clear(container);
        var p = document.createElement('p');
        p.className = 'empty-state';
        p.textContent = text;
        container.appendChild(p);
    }

    function render(container, data) {
        if (!container || !data) {
            return;
        }

        var buckets = data.buckets || [];
        var series = (data.series || []).filter(function (item) {
            return item.values && item.values.length > 0;
        });

        if (data.selection_empty) {
            emptyState(container, 'Статьи не выбраны — отметьте хотя бы одну');
            return;
        }

        if (!buckets.length || !series.length) {
            emptyState(container, 'Нет операций за выбранный период');
            return;
        }

        var maxValue = 0;
        series.forEach(function (item) {
            item.values.forEach(function (value) {
                if (value > maxValue) {
                    maxValue = value;
                }
            });
        });

        if (maxValue <= 0) {
            emptyState(container, 'Нет операций за выбранный период');
            return;
        }

        clear(container);

        var width = Math.max(container.clientWidth || 320, 280);
        var plotWidth = width - PAD.left - PAD.right;
        var plotHeight = HEIGHT - PAD.top - PAD.bottom;
        var top = niceMax(maxValue);
        var count = buckets.length;

        var xAt = function (index) {
            if (count === 1) {
                return PAD.left + plotWidth / 2;
            }
            return PAD.left + (plotWidth * index) / (count - 1);
        };
        var yAt = function (value) {
            return PAD.top + plotHeight - (plotHeight * value) / top;
        };

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + width + ' ' + HEIGHT,
            width: '100%',
            height: HEIGHT,
            role: 'img',
            'aria-label': 'График динамики по статьям, период: ' + (data.period && data.period.label ? data.period.label : '')
        });
        svg.classList.add('chart__svg');

        // Сетка и подписи оси Y
        for (var g = 0; g <= 4; g++) {
            var value = (top * g) / 4;
            var y = yAt(value);
            svg.appendChild(svgEl('line', {
                x1: PAD.left, y1: y, x2: width - PAD.right, y2: y,
                class: g === 0 ? 'chart__axis' : 'chart__grid'
            }));
            var label = svgEl('text', {
                x: PAD.left - 8, y: y + 3, 'text-anchor': 'end', class: 'chart__ylabel'
            });
            label.textContent = fmtCompact(value);
            svg.appendChild(label);
        }

        // Подписи оси X (прореживаем, чтобы не наезжали друг на друга)
        var step = Math.max(1, Math.ceil(count / 6));
        buckets.forEach(function (bucket, index) {
            if (index % step !== 0 && index !== count - 1) {
                return;
            }
            var text = svgEl('text', {
                x: xAt(index), y: HEIGHT - 8, 'text-anchor': 'middle', class: 'chart__xlabel'
            });
            text.textContent = bucket.label;
            svg.appendChild(text);
        });

        // Линии
        series.forEach(function (item, seriesIndex) {
            var color = PALETTE[seriesIndex % PALETTE.length];
            var d = '';
            item.values.forEach(function (value, index) {
                d += (index === 0 ? 'M' : 'L') + xAt(index).toFixed(1) + ' ' + yAt(value).toFixed(1) + ' ';
            });
            svg.appendChild(svgEl('path', {
                d: d.trim(),
                fill: 'none',
                stroke: color,
                'stroke-width': 2,
                'stroke-linejoin': 'round',
                'stroke-linecap': 'round'
            }));

            if (count <= 40) {
                item.values.forEach(function (value, index) {
                    svg.appendChild(svgEl('circle', {
                        cx: xAt(index).toFixed(1), cy: yAt(value).toFixed(1), r: 2.5,
                        fill: color
                    }));
                });
            }
        });

        // Вертикальная линия-курсор и точки активного бакета
        var cursor = svgEl('line', {
            x1: 0, y1: PAD.top, x2: 0, y2: PAD.top + plotHeight, class: 'chart__cursor'
        });
        cursor.style.display = 'none';
        svg.appendChild(cursor);

        container.appendChild(svg);

        // Тултип
        var tooltip = document.createElement('div');
        tooltip.className = 'chart__tooltip';
        tooltip.hidden = true;
        container.appendChild(tooltip);

        var hideCursor = function () {
            cursor.style.display = 'none';
            tooltip.hidden = true;
        };

        var showAt = function (clientX) {
            var rect = svg.getBoundingClientRect();
            var scale = width / rect.width;
            var x = (clientX - rect.left) * scale;
            var ratio = count === 1 ? 0 : (x - PAD.left) / plotWidth;
            var index = Math.round(ratio * (count - 1));
            index = Math.min(count - 1, Math.max(0, index));

            var cx = xAt(index);
            cursor.setAttribute('x1', cx);
            cursor.setAttribute('x2', cx);
            cursor.style.display = '';

            var rows = series
                .map(function (item, seriesIndex) {
                    var color = PALETTE[seriesIndex % PALETTE.length];
                    return '<div class="chart__tooltip-row">'
                        + '<span class="chart__dot" style="background:' + color + '"></span>'
                        + '<span class="chart__tooltip-name">' + escapeHtml(item.name) + '</span>'
                        + '<span class="chart__tooltip-value">' + fmtFull(item.values[index] || 0) + '</span>'
                        + '</div>';
                })
                .join('');

            tooltip.innerHTML = '<div class="chart__tooltip-title">'
                + escapeHtml(buckets[index].label_full || buckets[index].label) + '</div>' + rows;
            tooltip.hidden = false;

            var left = (cx / scale) - tooltip.offsetWidth / 2;
            left = Math.max(4, Math.min(left, rect.width - tooltip.offsetWidth - 4));
            tooltip.style.left = left + 'px';
            tooltip.style.top = '4px';
        };

        svg.addEventListener('pointermove', function (event) {
            showAt(event.clientX);
        });
        svg.addEventListener('pointerdown', function (event) {
            showAt(event.clientX);
        });
        svg.addEventListener('pointerleave', hideCursor);

        // Перерисовка при изменении ширины контейнера
        container._chartData = data;
        if (!container._chartObserver && typeof ResizeObserver !== 'undefined') {
            var lastWidth = container.clientWidth;
            container._chartObserver = new ResizeObserver(function () {
                if (Math.abs(container.clientWidth - lastWidth) < 8) {
                    return;
                }
                lastWidth = container.clientWidth;
                if (container._chartData) {
                    render(container, container._chartData);
                }
            });
            container._chartObserver.observe(container);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"');
    }

    window.BudgetChart = {
        render: render,
        palette: function () {
            return PALETTE.slice();
        },
        formatMoney: fmtFull
    };
})();
