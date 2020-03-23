package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt" // пакет для форматированного ввода вывода
	"io"
	"io/ioutil"
	"log" // пакет для логирования
	"mime"
	"net"
	"net/http" // пакет для поддержки HTTP протокола
	"os"
	"path/filepath"
	"regexp"
	"strings" // пакет для работы с  UTF-8 строками

	"gopkg.in/yaml.v2"
)

const (
	DELIMITER   byte = '\n'
	SERVER_PATH int  = iota
	SERVER_REQUEST
	SERVER_SCHEME
)

type Config struct {
	Version string
	Main    struct {
		WwwPath string `yaml:"www_path"`
	} `yaml:"main"`
}

type Response struct {
	Url     string
	Request string
	Method  string
	Scheme  []string
	Form    string
	Headers []string
	Body    []byte
}

func HomeRouterHandler(w http.ResponseWriter, r *http.Request) {
	r.ParseForm()
	//r.ParseMultipartForm(1024)

	path := r.URL.Path

	if isStaticFile(path) {
		staticPath := getStaticFilesPath()
		path = staticPath + path
		img, err := os.Open(path)
		if err != nil {
			log.Fatal(err)
		}
		defer img.Close()

		ext := filepath.Ext(path)

		w.Header().Set("Content-Type", mime.TypeByExtension(ext))

		io.Copy(w, img)
		return
	}

	var urlString string
	for k, v := range r.Form {
		urlString = urlString + fmt.Sprintf("%s=%s&", k, strings.Join(v, ""))
	}

	var headersData []string
	for name, headers := range r.Header {
		name = strings.ToLower(name)
		for _, h := range headers {
			headersData = append(headersData, fmt.Sprintf("%v: %v", name, h))
		}
	}

	var form string
	form = r.Form.Encode()

	var scheme []string
	scheme = append(scheme, r.URL.Scheme)

	body, _ := ioutil.ReadAll(r.Body)

	response := Response{Url: path,
		Request: urlString,
		Method:  r.Method,
		Headers: headersData,
		Form:    form,
		Scheme:  scheme,
		Body:    body}

	res, _ := sendSocket(response)

	fmt.Fprint(w, res)
}

func getStaticFilesPath() string {
	conf, _ := getConfig()
	return conf.Main.WwwPath
}

func getConfig() (*Config, error) {
	file, err := os.Open("config_http.yaml")
	if err != nil {
		return nil, err
	}
	defer file.Close()

	config := &Config{}

	d := yaml.NewDecoder(file)

	// Start YAML decoding from file
	if err := d.Decode(&config); err != nil {
		return nil, err
	}

	return config, nil
}

func isStaticFile(path string) bool {
	staticExt := getStaticdExtentions()

	for _, ext := range staticExt {
		pattern := ext + `$`
		matched, _ := regexp.Match(pattern, []byte(path))

		if matched {
			return true
		}
	}

	return false
}

func getStaticdExtentions() []string {
	ext := []string{".jpg", ".jpeg", ".png", ".css", ".js"}

	return ext
}

func sendSocket(response Response) (string, error) {
	conn, err := net.Dial("unix", "/tmp/echo.sock")
	if err != nil {
		log.Fatal("net Dial Error %v", err)
		return "", err
	}

	send(conn, response)

	message, err := Read(conn, DELIMITER)

	return message, err
}

func send(conn net.Conn, response Response) {
	bResp, err := json.Marshal(response)
	if err != nil {
		log.Fatal("Marshal Json", err)
		return
	}
	conn.Write(bResp)
}

func Read(conn net.Conn, delim byte) (string, error) {
	reader := bufio.NewReader(conn)
	var buffer bytes.Buffer
	for {
		ba, isPrefix, err := reader.ReadLine()
		if err != nil {
			if err == io.EOF {
				break
			}
			return "", err
		}
		buffer.Write(ba)
		if !isPrefix {
			break
		}
	}
	return buffer.String(), nil
}

func main() {
	http.HandleFunc("/", HomeRouterHandler)
	err := http.ListenAndServe(":9000", nil)
	if err != nil {
		log.Fatal("ListenAndServe: ", err)
	}
}
